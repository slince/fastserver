<?php

namespace Viso\Server;

use Symfony\Component\OptionsResolver\OptionsResolver;

interface PluginInterface
{
    /**
     * Returns the id of the plugin.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Call when activate the plugin.
     *
     * @param ServerInterface $server
     * @param array $options
     * @return void
     */
    public function activate(ServerInterface $server, array $options): void;

    /**
     * Configure options resolver for the plugin.
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void;
}