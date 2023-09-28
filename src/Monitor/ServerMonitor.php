<?php

namespace Waveman\Monitor;

use React\Socket\SocketServer;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Waveman\Server\PluginInterface;
use Waveman\Server\ServerInterface;

final class ServerMonitor implements PluginInterface
{
    private ServerInterface $server;
    private array $options;
    private SocketServer $socketServer;
    
    public function getId(): string
    {
        return 'monitor';
    }

    public function activate(ServerInterface $server): void
    {
        $this->server = $server;
        $this->socketServer = new SocketServer($this->options['address'], $this->options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->define('address')
            ->allowedTypes('string')
            ->info('The monitor address')
            ->default(function(){
                if ('\\' === DIRECTORY_SEPARATOR) {
                    return 'tcp://127.0.0.1:9003';
                }
                return sprintf('unix://%s/waveman.sock', getcwd());
            });
        $resolver->setDefaults([
            'tcp' => [],
            'tls' => [],
            'unix' => [],
        ]);
    }

    public function configure(array $options): void
    {
        $this->options = $options;
    }
}