<?php

namespace Waveman\Server;

use Symfony\Component\OptionsResolver\OptionsResolver;

interface PluginInterface
{
    public function getId(): string;

    public function activate(ServerInterface $server): void;

    public function configureOptions(OptionsResolver $resolver): void;

    public function configure(array $options): void;
}