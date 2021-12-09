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

final class UnixServer extends Server
{
    public function __construct($socket, LoopInterface $loop = null)
    {
        $this->loop = $loop ?: Loop::get();
        $this->master = $socket;
        \stream_set_blocking($this->master, false);
        $this->resume();
    }

    public static function createSocket(string $path, array $context = array())
    {
        if (\strpos($path, '://') === false) {
            $path = 'unix://' . $path;
        } elseif (\substr($path, 0, 7) !== 'unix://') {
            throw new \InvalidArgumentException('Given URI "' . $path . '" is invalid');
        }

        $socket = @\stream_socket_server(
            $path,
            $errno,
            $errstr,
            \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN,
            \stream_context_create(['socket' => $context])
        );
        if (false === $socket) {
            // PHP does not seem to report errno/errstr for Unix domain sockets (UDS) right now.
            // This only applies to UDS server sockets, see also https://3v4l.org/NAhpr.
            // Parse PHP warning message containing unknown error, HHVM reports proper info at least.
            if ($errno === 0 && $errstr === '') {
                $error = \error_get_last();
                if (\preg_match('/\(([^\)]+)\)|\[(\d+)\]: (.*)/', $error['message'], $match)) {
                    $errstr = isset($match[3]) ? $match['3'] : $match[1];
                    $errno = isset($match[2]) ? (int)$match[2] : 0;
                }
            }

            throw new \RuntimeException('Failed to listen on Unix domain socket "' . $path . '": ' . $errstr, $errno);
        }

        return $socket;
    }

    public function getAddress(): ?string
    {
        if (!\is_resource($this->master)) {
            return null;
        }

        return 'unix://' . \stream_socket_get_name($this->master, false);
    }
}
