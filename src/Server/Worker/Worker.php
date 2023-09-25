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

namespace Waveman\Server\Worker;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use Waveman\Server\ServerInterface;

abstract class Worker
{
    /**
     * @var int
     */
    protected int $id;

    /**
     * @var ServerInterface
     */
    protected ServerInterface $server;

    /**
     * @var LoopInterface
     */
    protected LoopInterface $loop;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

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

    protected function run(): void
    {
        if ($this->server->getOption('reuseport')) {
            $socket = $this->server->createSocket();
        } else {
            $socket = $this->server->getSocket();
        }
        $socket->on('connection', [$this, 'handleConnection']);
        $socket->on('error', [$this, 'handleError']);
    }

    /**
     * Handles the new connection.
     * {@internal}
     * @param ConnectionInterface $connection
     * @return void
     */
    public function handleConnection(ConnectionInterface $connection): void
    {
        $this->logger->debug(sprintf('Worker [%s] [%s] Accept connection from %s', $this->getId(),
            $this->getPid(), $connection->getLocalAddress()));
        $this->server->emit('connection', [$connection]);
    }

    /**
     * Handles the error.
     * {@internal}
     * @param \Exception $error
     * @return void
     */
    public function handleError(\Exception $error): void
    {
        $this->server->emit('error', [$error]);
    }

    /**
     * Starts the worker.
     */
    abstract public function start(): void;

    /**
     * Close the worker.
     */
    abstract public function close(bool $graceful = false): void;
}