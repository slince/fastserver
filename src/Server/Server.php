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
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface as Socket;
use React\Socket\SocketServer;
use Slince\Process\Process;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Waveman\Server\Exception\InvalidArgumentException;

final class Server extends EventEmitter implements ServerInterface
{
    private const EVENT_NAMES = ['start', 'end', 'connection', 'message', 'close'];

    /**
     * @var array
     */
    protected array $options;

    /**
     * @var Socket
     */
    protected Socket $socket;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var LoopInterface
     */
    protected LoopInterface $loop;

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
    protected function configureOptions(OptionsResolver $resolver): void
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
        $this->boot();
        $this->emit('start', [$this]);
        $this->logger->info(sprintf('The server is listen on %s', $this->options['address']));
    }

    private function boot(): void
    {
        $this->trySignals();
        if (!$this->options['reuseport'] || !Process::isSupported()) {
            $this->createSocket();
        }
    }

    /**
     * Attempt install signals.
     *
     * @return void
     */
    protected function trySignals(): void
    {
        try {
            $this->loop->addSignal(\SIGINT, [$this, 'onSignal']);
            $this->loop->addSignal(\SIGTERM, [$this, 'onSignal']);
            $this->loop->addSignal(\SIGQUIT, [$this, 'onSignal']);
            $this->loop->addSignal(\SIGHUP, [$this, 'onSignal']);
            $this->loop->addSignal(\SIGUSR1, [$this, 'onSignal']);
            $this->loop->addSignal(\SIGUSR2, [$this, 'onSignal']);
            $this->loop->addSignal(\SIGCHLD, [$this, 'onSignal']);
            $this->logger->debug("Register signals successfully.");
        } catch (\Exception $e) {
            $this->logger->warning("Cannot register signals.");
        }
    }

    public function createSocket(): Socket
    {
        return $this->socket = new SocketServer($this->options['address'], $this->options, $this->loop);
    }

    /**
     * {@inheritdoc}
     */
    public function getSocket(): Socket
    {
        return $this->socket;
    }

    /**
     * The method may be executed in child process for some worker type.
     *
     * @internal
     * @param ConnectionInterface $connection
     */
    public function handleConnection(ConnectionInterface $connection): void
    {
        $this->logger->debug(sprintf('Worker [%s] [%s] Accept connection from %s', $this->worker->getId(),
            $this->worker->getPid(), $connection->getLocalAddress()));
        $this->emit('connection', [$connection]);
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
}