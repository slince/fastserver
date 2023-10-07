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
use Waveman\Channel\ChannelInterface;
use Waveman\Channel\CommandInterface;
use Waveman\Cluster\Command\WorkerPingCommand;
use Waveman\Cluster\Exception\LogicException;
use Waveman\Cluster\Exception\RuntimeException;
use Waveman\Server\Command\CloseCommand;
use Waveman\Server\Command\HeartbeatCommand;

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

    protected Cluster $cluster;

    private $callback;

    public function __construct(int $id, Cluster $cluster, callable $callback = null)
    {
        $this->id = $id;
        $this->cluster = $cluster;
        $this->callback = $callback;
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
    public function run(): void
    {
        $this->requireInChildProcess(__METHOD__);
        if ($this->status !== self::STATUS_READY) {
            throw new RuntimeException('The worker is already running.');
        }
        if (null !== $this->callback) {
            call_user_func($this->callback, $this->cluster);
        }
        $this->status = self::STATUS_STARTED;
        $this->emit('start');
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
        $this->emit('close', [true]);
    }

    /**
     * {@internal}
     */
    public function handleCommand(CommandInterface $command): void
    {
        // dispatch command event.
        switch ($command->getCommandId()) {
            // for child process.
            case 'NOP':
                break;
            case 'CLOSE':
                $this->handleClose($command->isGraceful());
                break;
            case 'HEARTBEAT':
                $this->control->send(new WorkerPingCommand($this->getPid()));
                break;
            // for main process.
            case 'WORKER_PING':
                $this->heartbeat();
                break;
            default:
                $this->emit('command', [$command]);
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
        $command = new CloseCommand($graceful);
        $this->control->send($command);
        $this->status = self::STATUS_CLOSING;
    }

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
        $command = new HeartbeatCommand();
        $this->control->send($command);
    }

    /**
     * Close the worker.
     * {@internal}
     * @param bool $graceful
     * @return void
     */
    protected function handleClose(bool $graceful): void
    {
        $this->requireInChildProcess(__METHOD__);
        $this->status = self::STATUS_TERMINATED;
        $this->emit('close', [$graceful]);
    }

    protected function requireInChildProcess(string $method): void
    {
        if (!$this->cluster->isPrimary) {
            throw new LogicException(sprintf('The method %s can only be executed in child process.', $method));
        }
    }

    protected function requireInMainProcess(string $method): void
    {
        if ($this->cluster->isPrimary) {
            throw new LogicException(sprintf('The method %s can only be executed in main process.', $method));
        }
    }
}