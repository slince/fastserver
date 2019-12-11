<?php

namespace FastServer;

use Evenement\EventEmitter;
use FastServer\Socket\Server;
use FastServer\Worker\Worker;
use HttpServer\Exception\InvalidArgumentException;
use React\EventLoop\Factory as LoopFactory;
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
     * @var resource
     */
    protected $socket;

    /**
     * @var LoopInterface
     */
    protected $loop;

    public function __construct()
    {
        $this->loop = LoopFactory::create();
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
    public function on($event, callable $listener)
    {
        if (!in_array($event, $this->options['event_names'])) {
            throw new InvalidArgumentException(sprintf('The event "%s" is not supported.', $event));
        }
        parent::on($event, $listener);
    }

    /**
     * {@inheritdoc}
     */
    public function serve()
    {
        $socket = $this->createSocket();
        $this->pool = $this->createWorkers($socket);
        $this->loop = LoopFactory::create();
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

    protected function createSocket()
    {
        return Server::createSocket($this->options['address']);
    }

    protected function createWorkers($socket)
    {
        $pool = new WorkerPool();
        for ($i = 0; $i <= $this->options['work_num']; $i++) {
            $pool->add(new Worker($this, $socket));
        }
        return $pool;
    }

    /**
     * Configure options resolver for the server.
     *
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([ 'max_workers' => 1])
            ->setDefined([
                'event_names' => ['start', 'end', 'client-connect']
            ]);
    }
}