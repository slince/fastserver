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

namespace FastServer\Worker;

use FastServer\ServerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface as Socket;

class Worker
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var ServerInterface
     */
    protected $server;

    /**
     * @var Socket
     */
    protected $socket;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var callable[]
     */
    protected $signals = [];

    public function __construct(int $id, LoopInterface $loop, ServerInterface $server)
    {
        $this->id = $id;
        $this->loop = $loop;
        $this->server = $server;
        $this->socket = $server->getSocket();
    }

    /**
     * Return the worker id.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Return the worker pid.
     *
     * @return int
     */
    public function getPid(): int
    {
        return getmypid();
    }

    /**
     * Starts the worker.
     */
    public function start()
    {
        $this->work();
    }

    /**
     * Close the worker.
     *
     * {@internal}
     */
    public function close()
    {
        // ignore this
    }

    /**
     * @internal
     */
     public function work()
     {
         $this->server->setWorker($this);
         $this->socket->on('connection', [$this->server, 'handleConnection']);
         $this->socket->on('error', [$this->server, 'handleError']);
     }
}