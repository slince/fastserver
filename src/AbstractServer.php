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

namespace FastServer;

use Evenement\EventEmitter;
use FastServer\Worker\WorkerFactory;
use FastServer\Worker\WorkerPool;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface as Socket;
use FastServer\Exception\InvalidArgumentException;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractServer extends EventEmitter implements ServerInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var WorkerPool
     */
    protected $pool;

    /**
     * @var Socket
     */
    protected $socket;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var Worker\Worker
     */
    protected $worker;

    public function __construct(?LoggerInterface $logger = null, ?LoopInterface $loop = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->loop = $loop ?? Loop::get();
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        $optionsResolver = new OptionsResolver();
        $this->configureOptions($optionsResolver);
        $this->options = $optionsResolver->resolve($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption(string $name)
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Configure options resolver for the server.
     *
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'reuseport' => false,
                'max_workers' => 1,
                'event_names' => $this->allowedEventNames()
            ])
            ->setRequired(['address']);
    }

    /**
     * Return allowed event names by this server.
     *
     * @return array
     */
    protected function allowedEventNames(): array
    {
        return ['start', 'end', 'connection', 'message', 'close'];
    }

    /**
     * {@inheritdoc}
     */
    public function on($event, callable $listener)
    {
        if (!in_array($event, $this->options['event_names'])) {
            throw new InvalidArgumentException(sprintf('The event "%s" is not supported.', $event));
        }
        return parent::on($event, $listener);
    }

    /**
     * @internal
     */
    public function handleError(\Exception $e)
    {
        $this->emit('error', [$e]);
    }

    /**
     * {@inheritdoc}
     */
    public function getSocket(): Socket
    {
        return $this->socket;
    }

    /**
     * {@internal}
     */
    public function bindWorkerContext(Worker\Worker $worker)
    {
        $this->worker = $worker;
    }

    /**
     * The method may be executed in child process for some worker type.
     *
     * @internal
     * @param ConnectionInterface $connection
     */
    public function handleConnection(ConnectionInterface $connection)
    {
        $this->logger->debug(sprintf('Worker [%s] [%s] Accept connection from %s', $this->worker->getId(),
            $this->worker->getPid(), $connection->getLocalAddress()));
        $this->emit('connection', [$connection]);
    }

    /**
     * {@inheritdoc}
     */
    public function serve()
    {
        $this->boot();
        $this->emit('start', [$this]);
        $this->logger->info(sprintf('The server is listen on %s', $this->options['address']));
        $this->pool->run();
        if (null !== $this->socket) {
            $this->socket->close();
        }
        $this->pool->wait();
    }

    private function boot()
    {
        if (!$this->options['reuseport']) {
            $this->socket = $this->createSocket();
        }
        $this->pool = $this->createWorkers();
        $this->initialize();
        register_shutdown_function(function($err){
            $this->pool->close();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function quit()
    {
        $this->emit('stop');
        $this->loop->stop();
    }

    /**
     * Create worker pools.
     *
     * @return WorkerPool
     */
    private function createWorkers(): WorkerPool
    {
        $pool = WorkerFactory::create($this->options['max_workers'], $this, $this->logger);
        return $pool->build();
    }

    /**
     * Initialize the server.
     */
    protected function initialize()
    {
    }
}