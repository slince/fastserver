<?php

namespace FastServer;

use Symfony\Component\OptionsResolver\OptionsResolver;

class AbstractServer implements ServerInterface
{
    /**
     * @var array
     */
    protected $options;

    public function configure(array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
    }

    protected function configureOptions(OptionsResolver $resolver)
    {

    }
}