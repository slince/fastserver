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

use React\Socket\SecureServer;
use React\Socket\TcpServer as Socket;
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
        if ($this->options['reuseport']) {
            $this->options['tcp_context']['so_reuseport'] = true;
        }
        $server = new Socket($this->options['address'], $this->loop, $this->options['tcp_context']);
        if ($this->options['tls']) {
            $server = new SecureServer($server, $this->loop, $this->options['tls_context']);
        }
        return $server;
    }
}