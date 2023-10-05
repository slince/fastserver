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
use Waveman\Server\Channel\ChannelInterface;
use Waveman\Server\Channel\CommandInterface;
use Waveman\Server\Command\ControlCommand;
use Waveman\Server\Command\WorkerConnectionsCommand;
use Waveman\Server\Command\WorkerPingCommand;
use Waveman\Server\Command\WorkerStatusCommand;
use Waveman\Server\ConnectionDescriptor;
use Waveman\Server\ConnectionPool;
use Waveman\Server\Server;
use Waveman\Server\ServerInterface;
use Waveman\Server\WorkerStatus;

abstract class Worker
{
    /**
     * process status,running
     * @var string
     */
    const STATUS_READY = 'ready';

    /**
     * process status,running
     * @var string
     */
    const STATUS_STARTED = 'started';

    /**
     * closing.
     */
    const STATUS_CLOSING = 'closing';

    /**
     * process status,terminated
     * @var string
     */
    const STATUS_TERMINATED = 'terminated';

    /**
     * @var string
     */
    protected string $status = self::STATUS_READY;

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

    /**
     * @var ConnectionPool
     */
    protected ConnectionPool $connections;

    /**
     * Created time.
     *
     * @var \DateTimeInterface
     */
    protected \DateTimeInterface $createdAt;

    /**
     * Update time.
     *
     * @var \DateTimeInterface
     */
    protected \DateTimeInterface $updatedAt;

    public function __construct(int $id, Server $server)
    {
        $this->id = $id;
        $this->server = $server;
        $this->connections = $server->getConnections();
        $this->loop = $server->getLoop();
        $this->logger = $server->getLogger();
        $this->createdAt = $this->updatedAt = new \DateTime();
    }

    /**
     * Return the worker status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
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
     * Close the worker.
     * {@internal}
     * @param bool $grace
     * @return void
     */
    abstract public function handleClose(bool $grace): void;

    /**
     * Heartbeat.
     *
     * @return void
     */
    public function heartbeat(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Mark the worker terminated.
     * @return void
     */
    public function terminate(): void
    {
        $this->status = self::STATUS_TERMINATED;
    }

    /**
     * Capture the worker status.
     *
     * @return WorkerStatus
     */
    protected function createStatus(): WorkerStatus
    {
        return new WorkerStatus(
            $this->getPid(),
            $this->server->getOption('address'),
            memory_get_usage(false),
            $this->connections->count()
        );
    }

    /**
     * {@internal}
     */
    public function handleCommand(CommandInterface $command): void
    {
        // dispatch command event.
        switch ($command->getCommandId()) {
            // for child process.
            case 'CLOSE':
                $this->handleClose($command->isGraceful());
                break;
            case 'HEARTBEAT':
                $this->getControl()->send(new WorkerPingCommand($this->getPid()));
                break;
            case 'CONTROL':
                if (($command->getFlags() & ControlCommand::CONNECTIONS) === ControlCommand::CONNECTIONS) {
                    $this->getControl()->send(new WorkerConnectionsCommand($this->getPid(), ConnectionDescriptor::fromConnectionPool($this->connections)));
                }
                if (($command->getFlags() & ControlCommand::STATUS) === ControlCommand::STATUS) {
                    $this->getControl()->send(new WorkerStatusCommand($this->getPid(), $this->createStatus()));
                }
                break;
            default:
                // for master process.
                $this->server->handleCommand($command);
        }
    }

    /**
     * Gets create time.
     *
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Gets the update time.
     *
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Alive seconds.
     *
     * @return int
     */
    public function getAliveSeconds(): int
    {
        return time() - $this->createdAt->getTimestamp();
    }

    /**
     * Return the worker's control channel.
     *
     * @return ChannelInterface
     */
    abstract public function getControl(): ChannelInterface;

    /**
     * Starts the worker.
     */
    abstract public function start(): void;

    /**
     * Close the worker.
     */
    abstract public function close(bool $graceful = false): void;

    /**
     * Checks the worker is alive.
     *
     * @return void
     */
    abstract public function alive(): void;
}