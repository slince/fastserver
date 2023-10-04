<?php

declare(strict_types=1);

/*
 * This file is part of the waveman/waveman package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Waveman\Server;

use Evenement\EventEmitter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Slince\Process\Process;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Waveman\Server\Channel\ChannelInterface;
use Waveman\Server\Channel\CommandInterface;
use Waveman\Server\Channel\SignalChannel;
use Waveman\Server\Command\CloseCommand;
use Waveman\Server\Command\WorkerCloseCommand;
use Waveman\Server\Exception\InvalidArgumentException;
use Waveman\Server\Worker\WorkerPool;

final class Server extends EventEmitter implements ServerInterface
{
    private const EVENT_NAMES = ['start', 'connection', 'close', 'error', 'command'];

    /**
     * @var array
     */
    private array $options;

    /**
     * @var SocketServer|null
     */
    private ?SocketServer $socket = null;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @var WorkerPool
     */
    private WorkerPool $workers;

    /**
     * @var ChannelInterface
     */
    private ChannelInterface $signals;

    private ConnectionPool $connections;

    public function __construct(array $options, ?LoggerInterface $logger = null, ?LoopInterface $loop = null)
    {
        $this->configure($options);
        $this->logger = $logger ?? new NullLogger();
        $this->loop = $loop ?? Loop::get();
        $this->connections = new ConnectionPool();
    }

    /**
     * Configure the server.
     *
     * @param array $options
     */
    private function configure(array $options): void
    {
        $optionsResolver = new OptionsResolver();
        $this->configureOptions($optionsResolver);
        $this->options = $optionsResolver->resolve($options);
        foreach ($this->options['plugins'] as $plugin) {
            $this->configurePlugin($plugin, $options[$plugin->getId()] ?? []);
        }
        if ($this->options['reuseport']) {
            $this->options['tls']['so_reuseport'] = true;
        }
    }

    private function configurePlugin(PluginInterface $plugin, array $options): void
    {
        $optionsResolver = new OptionsResolver();
        $plugin->configureOptions($optionsResolver);
        $resolved = $optionsResolver->resolve($options);
        $plugin->configure($resolved);
    }

    /**
     * Gets the specific option.
     *
     * @param string $name
     * @return mixed
     */
    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Returns the connection pool of the server.
     *
     * @return ConnectionPool
     */
    public function getConnections(): ConnectionPool
    {
        return $this->connections;
    }

    /**
     * Return the logger instance.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return LoopInterface
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    /**
     * Configure options resolver for the server.
     *
     * @param OptionsResolver $resolver
     */
    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'reuseport' => false,
                'max_workers' => 1,
                'plugins' => [],
            ])
            ->setAllowedTypes('plugins', [PluginInterface::class . '[]'])
            ->setRequired(['address'])
            ->setIgnoreUndefined()
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function on($event, callable $listener): void
    {
        if (!in_array($event, self::EVENT_NAMES)) {
            throw new InvalidArgumentException(sprintf('The event "%s" is not supported.', $event));
        }
        parent::on($event, $listener);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(bool $graceful = false): void
    {
        $this->workers->close($graceful);
        $this->emit('stop');
        $this->loop->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function serve(): void
    {
        $this->boot();
        $this->emit('start', [$this]);
        $this->logger->info(sprintf('The server is listen on %s', $this->options['address']));
        $this->workers->run();
        $this->loop->run();
    }

    private function boot(): void
    {
        if (!$this->options['reuseport'] || !Process::isSupported()) {
            $this->getSocket();
        }
        $this->createChannel();
        $this->createWorkers();
        $this->activatePlugins();
    }

    private function createChannel(): void
    {
        // if the signal is supported, create it.
        if (Process::isSupportPosixSignal()) {
            $this->signals = new SignalChannel(null, $this->loop, [
                \SIGTERM => new CloseCommand(true),
                \SIGHUP => new CloseCommand(true),
                \SIGINT => new CloseCommand(true),
                \SIGQUIT => new CloseCommand(true),
                \SIGCHLD => new WorkerCloseCommand()
            ]);
            $this->signals->listen([$this, 'handleCommand']);
            $this->logger->debug("Register signals successfully.");
        } else {
            $this->logger->warning("Cannot register signals.");
        }
    }

    /**
     * Handle commands.
     * 
     * {@internal}
     * @param CommandInterface $command
     * @return void
     */
    public function handleCommand(CommandInterface $command): void
    {
        $this->emit('command', [$command]);
        switch ($command->getCommandId()) {
            case 'CLOSE':
                $this->stop($command->isGraceful());
                break;
            case 'RELOAD':
                $this->logger->debug('Reload workers.');
                $this->workers->restartAll();
                break;
            // Command from workers.
            case 'PING':
                $this->logger->debug(sprintf('Received ping from worker %d.', $command->getWorkerId()));
                $this->workers->heartbeat($command->getWorkerId());
                break;
            case 'WORKER_CLOSE':
                // Only for that enabled sigchid
                $pid = \pcntl_wait($status);
                if (-1 === $pid) {
                    return;
                }
                $this->logger->debug(sprintf('Checked that the worker %d has exited, restart a new worker', $pid));
                $this->workers->removeByPid($pid);
                break;
        }
    }

    private function createWorkers(): void
    {
        $this->logger->debug(sprintf("Create %d workers.", $this->options['max_workers']));
        $this->workers = WorkerPool::createPool($this->options['max_workers'], $this);
    }

    private function activatePlugins(): void
    {
        $this->logger->debug('Activate plugins.');
        foreach ($this->options['plugins'] as $plugin) {
            $plugin->activate($this);
        }
    }

    /**
     * Returns the socket instance.
     *
     * @return SocketServer
     */
    public function getSocket(): SocketServer
    {
        if (null === $this->socket) {
            $this->socket = new SocketServer($this->options['address'], $this->options, $this->loop);
        }
        return $this->socket;
    }
}