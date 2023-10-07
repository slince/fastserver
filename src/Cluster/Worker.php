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

namespace Waveman\Cluster;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;
use Waveman\Channel\ChannelInterface;
use Waveman\Cluster\Exception\RuntimeException;

abstract class Worker extends EventEmitter
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
     * @var int
     */
    protected int $id;

    /**
     * @var string
     */
    protected string $status = self::STATUS_READY;

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

    /**
     * @var ChannelInterface
     */
    protected ChannelInterface $control;

    /**
     * Whether in the child process.
     * @var bool
     */
    protected bool $inChildProcess = false;

    public function __construct(int $id)
    {
        $this->id = $id;
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
        $this->requireInChildProcess(__METHOD__);
        if ($this->status !== self::STATUS_READY) {
            throw new RuntimeException('The worker is already running.');
        }
        $socket = $this->server->getSocket();
        $socket->on('connection', [$this, 'handleConnection']);
        $socket->on('error', [$this, 'handleError']);
        $this->status = self::STATUS_STARTED;
        $this->server->emit('worker.start');
    }

    /**
     * Handles the new connection.
     * {@internal}
     * @param ConnectionInterface $connection
     * @return void
     */
    public function handleConnection(ConnectionInterface $connection): void
    {
        $this->requireInChildProcess(__METHOD__);
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
        $this->requireInChildProcess(__METHOD__);
        $this->logger->error(sprintf('Worker [%s] [%s] Accept connection error %s', $this->getId(), $this->getPid(), $error));
        $this->server->emit('error', [$error]);
    }

    /**
     * Heartbeat.
     *
     * @return void
     */
    public function heartbeat(): void
    {
        $this->requireInMainProcess(__METHOD__);
        $this->updatedAt = new \DateTime();
    }

    /**
     * Mark the worker terminated.
     * @return void
     */
    public function terminate(): void
    {
        $this->requireInMainProcess(__METHOD__);
        $this->status = self::STATUS_TERMINATED;
    }

    /**
     * Capture the worker status.
     *
     * @return WorkerStatus
     */
    protected function createStatus(): WorkerStatus
    {
        $this->requireInChildProcess(__METHOD__);
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
        $this->logger->debug(sprintf('Received command %s', $command->getCommandId()), ['pid' => getmypid()]);
        switch ($command->getCommandId()) {
            // for child process.
            case 'CLOSE':
                $this->handleClose($command->isGraceful());
                break;
            case 'HEARTBEAT':
                $this->control->send(new WorkerPingCommand($this->getPid()));
                break;
            case 'CONTROL':
                if (($command->getFlags() & ControlCommand::CONNECTIONS) === ControlCommand::CONNECTIONS) {
                    $this->control->send(new WorkerConnectionsCommand($this->getPid(), ConnectionDescriptor::fromConnectionPool($this->connections)));
                }
                if (($command->getFlags() & ControlCommand::STATUS) === ControlCommand::STATUS) {
                    $this->control->send(new WorkerStatusCommand($this->getPid(), $this->createStatus()));
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
        $this->requireInMainProcess(__METHOD__);
        return $this->createdAt;
    }

    /**
     * Gets the update time.
     *
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        $this->requireInMainProcess(__METHOD__);
        return $this->updatedAt;
    }

    /**
     * Alive seconds.
     *
     * @return int
     */
    public function getAliveSeconds(): int
    {
        $this->requireInMainProcess(__METHOD__);
        return time() - $this->createdAt->getTimestamp();
    }

    /**
     * Starts the worker.
     */
    public function start(): void
    {
        $this->requireInMainProcess(__METHOD__);
        if ($this->status !== self::STATUS_READY) {
            throw new RuntimeException('The worker is already running.');
        }
        $this->doStart();
        $this->status = self::STATUS_STARTED;
    }

    /**
     * Actual execution start method.
     */
    abstract protected function doStart(): void;

    /**
     * Close the worker.
     */
    public function close(bool $graceful = false): void
    {
        $this->requireInMainProcess(__METHOD__);
        if ($this->status !== self::STATUS_STARTED) {
            throw new RuntimeException('The worker is not running.');
        }
        $this->doClose($graceful);
        $this->status = self::STATUS_CLOSING;
    }

    /**
     * Actual close the worker.
     */
    abstract protected function doClose(bool $graceful = false): void;

    /**
     * Checks the worker is alive.
     *
     * @return void
     */
    public function alive(): void
    {
        $this->requireInMainProcess(__METHOD__);
        if ($this->status !== self::STATUS_STARTED) {
            throw new RuntimeException('The worker is not running.');
        }
        $this->doAlive();
    }

    /**
     * Checks the worker is alive.
     *
     * @return void
     */
    abstract protected function doAlive(): void;

    /**
     * Close the worker.
     * {@internal}
     * @param bool $graceful
     * @return void
     */
    public function handleClose(bool $graceful): void
    {
        $this->requireInChildProcess(__METHOD__);
        if ($graceful) {
            // close all connections of the worker.
            foreach ($this->connections as $connection => $_) {
                $connection->end();
            }
            // stop event loop
            $this->loop->stop();
        }
        $this->status = self::STATUS_TERMINATED;
        $this->server->emit('worker.close');
        $this->logger->info(sprintf('The worker %d is closed.', $this->getPid()));
        if ($graceful) {
            return;
        }
        exit(0);
    }

    protected function requireInChildProcess(string $method): void
    {
        if (!$this->inChildProcess) {
            throw new RuntimeException(sprintf('The method %s can only be executed in child process.', $method));
        }
    }

    protected function requireInMainProcess(string $method): void
    {
        if ($this->inChildProcess) {
            throw new RuntimeException(sprintf('The method %s can only be executed in main process.', $method));
        }
    }
}