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
    private const EVENT_NAMES = ['start', 'end', 'connection', 'message', 'close'];

    /**
     * @var array
     */
    private array $options;

    /**
     * @var SocketServer
     */
    private SocketServer $socket;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    private WorkerPool $pool;

    private ChannelInterface $signals;
    public function __construct(?LoggerInterface $logger = null, ?LoopInterface $loop = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->loop = $loop ?? Loop::get();
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $options): void
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
     * {@inheritdoc}
     */
    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
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
            ->setAllowedValues('plugins', [PluginInterface::class . '[]'])
            ->setRequired(['address'])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function on(string $event, callable $listener): void
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
        $this->pool->close($graceful);
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
        $this->pool->run();
        $this->loop->run();
    }

    private function boot(): void
    {
        if (!$this->options['reuseport'] || !Process::isSupported()) {
            $this->createSocket();
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

    private function handleCommand(CommandInterface $command): void
    {
        $this->emit('command', [$command]);
        switch ($command->getCommandId()) {
            case 'CLOSE':
                $this->stop($command->isGraceful());
                break;
            case 'WORKER_CLOSE':
                $pid = \pcntl_wait($status);
                if (-1 === $pid) {
                    return;
                }
                $this->logger->debug(sprintf('Checked that the worker %d has exited, restart a new worker', $pid));
                $this->pool->removeByPid($pid);
                break;
            case 'RELOAD':
                $this->logger->debug('Reload workers.');
                $this->pool->restartAll();
                break;
        }
    }

    private function createWorkers(): void
    {
        $this->pool = WorkerPool::createPool($this->options['max_workers'], $this, $this->loop, $this->logger);
    }

    private function activatePlugins(): void
    {
        foreach ($this->options['plugins'] as $plugin) {
            $plugin->activate($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createSocket(): SocketServer
    {
        return $this->socket = new SocketServer($this->options['address'], $this->options, $this->loop);
    }

    /**
     * {@inheritdoc}
     */
    public function getSocket(): SocketServer
    {
        return $this->socket;
    }
}