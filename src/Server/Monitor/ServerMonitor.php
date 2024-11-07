<?php

declare(strict_types=1);

/*
 * This file is part of the waveman/waveman package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Viso\Server\Monitor;

use React\Socket\SocketServer;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Viso\Server\PluginInterface;
use Viso\Server\ServerInterface;

final class ServerMonitor implements PluginInterface
{
    private ServerInterface $server;
    private array $options;
    private SocketServer $socketServer;
    
    public function getId(): string
    {
        return 'monitor';
    }

    public function activate(ServerInterface $server, array $options): void
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