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
use FastServer\Worker\Factory;
use FastServer\Worker\WorkerPool;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface as SocketServer;
use FastServer\Exception\InvalidArgumentException;
use React\EventLoop\LoopInterface;
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
     * @var SocketServer
     */
    protected $socket;

    /**
     * @var ParserFactoryInterface
     */
    protected $parserFactory;

    /**
     * @var LoopInterface
     */
    protected $loop;

    public function __construct(ParserFactoryInterface $parserFactory, ?LoopInterface $loop = null)
    {
        $this->parserFactory = $parserFactory;
        if (null === $loop) {
            $loop = Loop::get();
        }
        $this->loop = $loop;
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
        return ['start', 'end', 'connection'];
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
    public function pause()
    {
        $this->socket && $this->socket->pause();
    }

    /**
     * {@inheritdoc}
     */
    public function resume()
    {
        $this->socket && $this->socket->resume();
    }

    /**
     * {@inheritdoc}
     */
    public function getSocket(): SocketServer
    {
        return $this->socket;
    }

    /**
     * Gets the worker pool.
     *
     * @return WorkerPool
     * @internal
     */
    public function getPool(): WorkerPool
    {
        return $this->pool;
    }

    /**
     * @internal
     * @param ConnectionInterface $connection
     */
    public function handleConnection(ConnectionInterface $connection)
    {
        $this->emit('connection', [$connection]);
        $parser = $this->parserFactory->createParser($connection);
        $connection->on('data', function(string $chunk) use($connection, $parser){
            $parser->push($chunk);
            foreach ($parser->evaluate() as $message) {
                $this->emit('message', [$message, $connection]);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function serve()
    {
        $this->boot();
        $this->emit('start', [$this]);
        $this->pool->run();
    }

    private function boot()
    {
        $socket = $this->createSocketServer($this->options['address'], $this->loop);
        $this->pool = $this->createWorkers($socket);
        $this->socket = $socket;
        $this->initialize();
    }

    /**
     * Creates socket server for the given address.
     *
     * @param string $address
     * @param LoopInterface $loop
     * @return SocketServer
     */
    abstract protected function createSocketServer(string $address, LoopInterface $loop);

    /**
     * Create worker pools.
     *
     * @param SocketServer $socket
     * @return WorkerPool
     */
    private function createWorkers(SocketServer $socket): WorkerPool
    {
        $pool = Factory::create($this->options['max_workers']);
        $pool->resolve($this, $this->loop);
        return $pool;
    }

    /**
     * Initialize the server.
     */
    protected function initialize()
    {
        // custom boot
    }
}