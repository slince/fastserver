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
use Waveman\Cluster\Command\CloseCommand;
use Waveman\Cluster\Command\HeartbeatCommand;
use Waveman\Cluster\Command\MessageCommand;
use Waveman\Cluster\Command\WorkerPingCommand;
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
     * Return the worker status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Run the worker.
     * 
     * @return void
     */
    public function run(): void
    {
        $this->cluster->requireInChildProcess(__METHOD__);
        if ($this->status !== self::STATUS_READY) {
            throw new RuntimeException('The worker is already running.');
        }
        $this->doRun();
        if (null !== $this->callback) {
            call_user_func($this->callback, $this->cluster);
        }
        $this->status = self::STATUS_STARTED;
        $this->emit('start');
    }

    /**
     * Custom method when the worker is run.
     */
    protected function doRun(): void
    {
    }

    /**
     * Heartbeat.
     *
     * @return void
     */
    public function heartbeat(): void
    {
        $this->cluster->requireInMainProcess(__METHOD__);
        $this->updatedAt = new \DateTime();
    }

    /**
     * Mark the worker terminated.
     * @return void
     */
    public function terminate(): void
    {
        $this->status = self::STATUS_TERMINATED;
        $this->emit('close');
    }

    /**
     * Starts the worker.
     */
    public function start(): void
    {
        $this->cluster->requireInMainProcess(__METHOD__);
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
        $this->cluster->requireInMainProcess(__METHOD__);
        if ($this->status !== self::STATUS_STARTED) {
            throw new RuntimeException('The worker is not running.');
        }
        if ($graceful) {
            $this->send(new CloseCommand($graceful));
            $this->status = self::STATUS_CLOSING;
        } else {
            $this->doClose();
            $this->terminate();
        }
    }

    /**
     * Actual execution close method.
     */
    abstract protected function doClose(): void;

    /**
     * Send a signal to the worker process.
     *
     * @param int $signal
     * @return void
     */
    public function signal(int $signal): void
    {
        $this->cluster->requireInMainProcess(__METHOD__);
        if ($this->status !== self::STATUS_STARTED) {
            throw new RuntimeException('The worker is not running.');
        }
        $this->doSignal($signal);
    }

    /**
     * Actual execution signal method.
     */
    abstract protected function doSignal(int $signal): void;

    /**
     * Register signals handler for the worker.
     *
     * @param int|array $signals
     * @param callable|int $handler
     * @return void
     */
    public function onSignals(int|array $signals, callable|int $handler): void
    {
        $this->cluster->requireInChildProcess(__METHOD__);
        SignalHelper::registerSignals($signals, $handler);
    }

    /**
     * Checks the worker is alive.
     *
     * @return void
     */
    public function alive(): void
    {
        $this->cluster->requireInMainProcess(__METHOD__);
        if ($this->status !== self::STATUS_STARTED) {
            throw new RuntimeException('The worker is not running.');
        }
        $this->send(new HeartbeatCommand());
    }

    /**
     * Send command to the worker.
     *
     * @param CommandInterface $command
     * @return void
     */
    public function send(CommandInterface $command): void
    {
        $this->control->send($command);
    }

    /**
     * Send message to the worker.
     * @param string $message
     * @return void
     */
    public function sendMessage(string $message): void
    {
        $this->send(new MessageCommand($message));
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
                $this->terminate();
                break;
            case 'HEARTBEAT':
                $this->send(new WorkerPingCommand($this->getPid()));
                break;
            case 'MESSAGE':
                $this->emit('message', [$command->getMessage()]);
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
        $this->cluster->requireInMainProcess(__METHOD__);
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
}