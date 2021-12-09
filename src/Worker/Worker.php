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
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(int $id, ServerInterface $server, LoopInterface $loop, LoggerInterface $logger)
    {
        $this->id = $id;
        $this->server = $server;
        $this->loop = $loop;
        $this->logger = $logger;
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
    public function close(bool $grace = false)
    {
        // ignore this
    }

    /**
     * @internal
     */
     public function work()
     {
         $this->server->setWorker($this);
         if ($this->server->getOption('reuseport')) {
             $this->socket = $this->server->createSocket();
         } else {
             $this->socket = $this->server->getSocket();
         }
         $this->socket->on('connection', [$this->server, 'handleConnection']);
         $this->socket->on('error', [$this->server, 'handleError']);
     }
}