<?php

namespace FastServer\Server;

use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractServer implements ServerInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var callable
     */
    protected $listener;

    public function configure(array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
    }

    public function on($eventName, callable $listener)
    {
        $this->listener[$eventName] = $listener;
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([ 'max_workers' => 1])
            ->setDefined([
                'event_names' => ['start', 'end', 'client-connect']
            ]);
    }
}