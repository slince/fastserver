<?php

namespace FastServer;

use Evenement\EventEmitter;
use FastServer\Worker\Worker;
use FastServer\Exception\InvalidArgumentException;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\Socket\Server;
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
        $this->loop = LoopFactory::create();
        $socket = $this->createSocket();
        $this->pool = $this->createWorkers($socket);
        $this->initialize();
        $this->loop->run();
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
        return new Server($this->options['address'], $this->loop);
    }

    protected function createWorkers($socket)
    {
        $pool = new WorkerPool();
        for ($i = 0; $i < $this->options['max_workers']; $i++) {
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
        $resolver
            ->setDefaults([
                'max_workers' => 1,
                'event_names' => ['start', 'end', 'client-connect']
            ])
            ->setRequired(['address']);
    }

    protected function initialize()
    {
        foreach ($this->pool as $worker) {
            $worker->start();
        }
    }
}