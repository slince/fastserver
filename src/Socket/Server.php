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

use Evenement\EventEmitter;
use React\Socket\Connection;
use React\Socket\ServerInterface;

abstract class Server extends EventEmitter implements ServerInterface
{
    protected $master;
    protected $loop;
    protected $listening = false;

    public function pause()
    {
        if (!$this->listening) {
            return;
        }

        $this->loop->removeReadStream($this->master);
        $this->listening = false;
    }

    public function resume()
    {
        if ($this->listening || !is_resource($this->master)) {
            return;
        }

        $this->loop->addReadStream($this->master, function ($master) {
            $newSocket = @\stream_socket_accept($master, 0);
            if (false === $newSocket) {
                $this->emit('error', array(new \RuntimeException('Error accepting new connection')));

                return;
            }
            $this->handleConnection($newSocket);
        });
        $this->listening = true;
    }


    public function close()
    {
        if (!\is_resource($this->master)) {
            return;
        }

        $this->pause();
        \fclose($this->master);
        $this->removeAllListeners();
    }

    /** @internal */
    public function handleConnection($socket)
    {
        $this->emit('connection', array(
            new Connection($socket, $this->loop)
        ));
    }
}