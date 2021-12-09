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

namespace FastServer\Socket;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

class TcpServer extends Server
{
    public function __construct($socket, LoopInterface $loop = null)
    {
        $this->loop = $loop ?: Loop::get();

        $this->master = $socket;
        \stream_set_blocking($this->master, false);

        $this->resume();
    }

    public static function createSocket($uri, array $context = [])
    {
        // a single port has been given => assume localhost
        if ((string)(int)$uri === (string)$uri) {
            $uri = '127.0.0.1:' . $uri;
        }

        // assume default scheme if none has been given
        if (\strpos($uri, '://') === false) {
            $uri = 'tcp://' . $uri;
        }

        // parse_url() does not accept null ports (random port assignment) => manually remove
        if (\substr($uri, -2) === ':0') {
            $parts = \parse_url(\substr($uri, 0, -2));
            if ($parts) {
                $parts['port'] = 0;
            }
        } else {
            $parts = \parse_url($uri);
        }

        // ensure URI contains TCP scheme, host and port
        if (!$parts || !isset($parts['scheme'], $parts['host'], $parts['port']) || $parts['scheme'] !== 'tcp') {
            throw new \InvalidArgumentException('Invalid URI "' . $uri . '" given');
        }

        if (false === \filter_var(\trim($parts['host'], '[]'), \FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('Given URI "' . $uri . '" does not contain a valid host IP');
        }

        $socket = @\stream_socket_server(
            $uri,
            $errno,
            $errstr,
            \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN,
            \stream_context_create(['socket' => $context + ['backlog' => 511]])
        );

        if (false === $socket) {
            throw new \RuntimeException('Failed to listen on "' . $uri . '": ' . $errstr, $errno);
        }

        return $socket;
    }

    public function getAddress(): ?string
    {
        if (!\is_resource($this->master)) {
            return null;
        }

        $address = \stream_socket_get_name($this->master, false);

        // check if this is an IPv6 address which includes multiple colons but no square brackets
        $pos = \strrpos($address, ':');
        if ($pos !== false && \strpos($address, ':') < $pos && \substr($address, 0, 1) !== '[') {
            $address = '[' . \substr($address, 0, $pos) . ']:' . \substr($address, $pos + 1); // @codeCoverageIgnore
        }

        return 'tcp://' . $address;
    }
}