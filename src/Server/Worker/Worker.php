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

namespace Waveman\Server\Worker;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use Waveman\Server\ConnectionPool;
use Waveman\Server\Server;
use Waveman\Server\ServerInterface;

abstract class Worker
{
    /**
     * @var int
     */
    protected int $id;

    /**
     * @var Server
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

    protected ConnectionPool $connections;

    public function __construct(int $id, Server $server)
    {
        $this->id = $id;
        $this->server = $server;
        $this->connections = $server->getConnections();
        $this->loop = $server->getLoop();
        $this->logger = $server->getLogger();
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
     * Run the worker.
     * 
     * @return void
     */
    protected function run(): void
    {
        $socket = $this->server->getSocket();
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
        $this->server->getConnections()->add($connection);
        $connection->on('close', function() use($connection){
            $this->server->getConnections()->remove($connection);
        });
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