<?php

namespace FastServer;

use Evenement\EventEmitter;
use HttpServer\Exception\InvalidArgumentException;
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

    public function __construct()
    {
        $this->pool = new WorkerPool();
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

    protected function createSocket()
    {

    }
}