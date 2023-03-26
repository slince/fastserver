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

use React\EventLoop\LoopInterface;
use React\Socket\SecureServer;
use React\Socket\TcpServer as SocketServer;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TcpServer extends AbstractServer
{
    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'tcp_context' => [],
            'tls' => false,
            'tls_context' => []
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function createSocket()
    {
        if ($this->options['reuse_port']) {
            $this->options['tcp_context']['so_reuseport'] = true;
        }
        return Socket\TcpServer::createSocket($this->options['address'], $this->options['tcp_context']);
    }

    /**
     * {@inheritdoc}
     */
    public function createSocketServer($socket, LoopInterface $loop): \React\Socket\ServerInterface
    {
        $server = new SocketServer($this->options['address'], $loop, $this->options['tcp_context']);
        if ($this->options['tls']) {
            $server = new SecureServer($server, $loop, $this->options['tls_context']);
        }
        return $server;
    }
}