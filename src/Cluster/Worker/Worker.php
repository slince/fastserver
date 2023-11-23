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

namespace Viso\Cluster\Worker;

use Evenement\EventEmitter;
use Viso\Channel\ChannelInterface;
use Viso\Cluster\Cluster;
use Viso\Cluster\Command\CloseCommand;
use Viso\Cluster\Command\CommandFactory;
use Viso\Cluster\Command\CommandFactoryInterface;
use Viso\Cluster\Command\CommandInterface;
use Viso\Cluster\Command\ControlCommand;
use Viso\Cluster\Command\MessageCommand;
use Viso\Cluster\Command\PingCommand;
use Viso\Cluster\Exception\RuntimeException;
use Viso\Cluster\SignalUtils;

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

    protected CommandFactoryInterface $commandFactory;

    /**
     * The cluster of the worker.
     *
     * @var Cluster
     */
    protected Cluster $cluster;

    private $callback;

    public function __construct(int $id, Cluster $cluster, callable $callback = null)
    {
        $this->id = $id;
        $this->cluster = $cluster;
        $this->callback = $callback;
        $this->createdAt = $this->updatedAt = new \DateTime();
        $this->commandFactory = CommandFactory::create();
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
     * Checks whether the worker is running.
     * @return bool
     */
    abstract public function isRunning(): bool;

    /**
     * Run the worker.
     * 
     * @return void
     */
    public function run(): void
    {
        $this->cluster->requireInChildProcess(__METHOD__);
        $this->requireReady();
        $this->doRun();
        $this->cluster->loop->addPeriodicTimer(3, function(){
            echo 'send ping command', PHP_EOL;
            $this->sendCommand(new PingCommand($this->getId()));
        });
        if (null !== $this->callback) {
            call_user_func($this->callback, $this->cluster);
        }
        $this->status = self::STATUS_STARTED;
        $this->emit('start');
        $this->cluster->loop->run();
    }

    /**
     * Custom method when the worker is run.
     */
    protected function doRun(): void
    {
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
        $this->requireReady();
        $this->doStart();
        $this->status = self::STATUS_STARTED;
        $this->emit('start');
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
        $this->requireStarted();
        if ($graceful) {
            $this->sendCommand(new CloseCommand($graceful));
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
        $this->requireStarted();
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
        SignalUtils::registerSignals($signals, $handler);
    }

    /**
     * Check the worker status. send command to the worker.
     *
     * @return void
     */
    public function status(): void
    {
        $this->cluster->requireInMainProcess(__METHOD__);
        $this->requireStarted();
        $this->sendCommand(new ControlCommand(ControlCommand::STATUS));
    }

    /**
     * Check the worker connections. send command to the worker.
     *
     * @return void
     */
    public function connections(): void
    {
        $this->cluster->requireInMainProcess(__METHOD__);
        $this->requireStarted();
        $this->sendCommand(new ControlCommand(ControlCommand::CONNECTIONS));
    }

    /**
     * Send message to the worker.
     *
     * @param string $message
     * @return void
     */
    public function sendMessage(string $message): void
    {
        $this->sendCommand(new MessageCommand($message));
    }

    /**
     * Send command to the worker.
     *
     * @param CommandInterface $command
     * @return void
     */
    public function sendCommand(CommandInterface $command): void
    {
        $frame = $this->commandFactory->createFrame($command);
        $this->control->send($frame);
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
            case 'PONG':
                $this->updatedAt = new \DateTime();
                $this->emit('pong');
                break;
            case 'MESSAGE':
                $this->emit('message', [$command->getMessage()]);
                break;
            // for main process.
            case 'PING':
                $this->updatedAt = new \DateTime();
                $this->emit('ping');
                break;
            case 'STATUS':
                $this->emit('status', [$command->getStatus()]);
                break;
            case 'CONNECTIONS':
                $this->emit('connections', [$command->getConnections()]);
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

    private function requireStarted(): void
    {
        if ($this->status !== self::STATUS_STARTED) {
            throw new RuntimeException('The worker is not running.');
        }
    }

    private function requireReady(): void
    {
        if ($this->status !== self::STATUS_READY) {
            throw new RuntimeException('The worker is already running.');
        }
    }
}