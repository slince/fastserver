<?php

declare(strict_types=1);

/*
 * This file is part of the fastserver/fastserver package.
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
use Waveman\Server\Channel\UnixSocketChannel;
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
        if ($this->options['reuseport']) {
            $this->options['tls']['so_reuseport'] = true;
        }
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
            ])
            ->setRequired(['address']);
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
    public function stop(): void
    {
        $this->emit('stop');
        $this->loop->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function serve(): void
    {
        if (!$this->options['reuseport'] || !Process::isSupported()) {
            $this->createSocket();
        }
        $this->tryCreateChannel();
        $this->pool = WorkerPool::createPool($this->options['max_workers'], $this, $this->loop, $this->logger);
        $this->emit('start', [$this]);
        $this->logger->info(sprintf('The server is listen on %s', $this->options['address']));
    }

    private function tryCreateChannel(): void
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
            $this->logger->debug("Register signals successfully.");
        } else {
            $this->logger->warning("Cannot register signals.");
        }
        $channel = new UnixSocketChannel($this->sockets, $this->loop, $this->inChildProcess, self::createCommandFactory());
    }

    private function handleCommand(CommandInterface $command): void
    {
        switch ($command->getCommandId()) {
            case 'CLOSE':
                $this->handleClose($command->isGraceful());
                break;
            case 'WORK_CLOSE':
                break;
        }
    }

    /**
     * {@internal}
     */
    public function onSignal(int $signal): void
    {
        switch ($signal) {
            case \SIGINT:
            case \SIGTERM:
            case \SIGQUIT:
            case \SIGHUP:
                $this->pool->close(\SIGHUP === $signal);
                break;
            case \SIGUSR1:
            case \SIGUSR2:
                $this->pool->restart(\SIGUSR2 === $signal);
                break;
            case \SIGCHLD:
                $pid = \pcntl_wait($status);
                if (-1 === $pid) {
                    return;
                }
                $this->pool->removeWorker($pid);
                break;
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