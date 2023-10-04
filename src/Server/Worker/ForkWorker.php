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

use React\EventLoop\Factory;
use Slince\Process\Process;
use Waveman\Server\Channel\ChannelInterface;
use Waveman\Server\Channel\CommandInterface;
use Waveman\Server\Channel\SignalChannel;
use Waveman\Server\Channel\UnixSocketChannel;
use Waveman\Server\Command\CloseCommand;
use Waveman\Server\Command\CommandFactory;
use Waveman\Server\Command\ConnectionsCommand;
use Waveman\Server\Command\ControlCommand;
use Waveman\Server\Command\HeartbeatCommand;
use Waveman\Server\Command\PingCommand;
use Waveman\Server\Command\WorkerStatusCommand;
use Waveman\Server\Exception\RuntimeException;

final class ForkWorker extends Worker
{
    /**
     * @var Process
     */
    private Process $process;

    /**
     * @var ChannelInterface
     */
    private ChannelInterface $control;

    private array $sockets;

    private ?SignalChannel $signals = null;

    /**
     * Whether in the child process.
     * @var bool
     */
    private bool $inChildProcess = false;

    /**
     * {@inheritdoc}
     */
    public function getPid(): int
    {
        return $this->process->getPid();
    }

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        $this->sockets = UnixSocketChannel::createSocketPair();
        $this->process = new Process($this->createCallable());
        $this->createChannel();
        $this->process->start();
    }

    /**
     * {@inheritdoc}
     */
    public function close(bool $graceful = false): void
    {
        $this->control->send(new CloseCommand($graceful));
    }

    /**
     * {@inheritdoc}
     */
    public function alive(): void
    {
        $command = new HeartbeatCommand();
        if (null !== $this->signals) {
            $this->signals->send($command);
        } else {
            $this->control->send($command);
        }
    }


    private function createCallable(): \Closure
    {
        return function(){
            $this->inChildProcess = true;
            // Reset loop instance.
            $this->loop = Factory::create();
            $this->createChannel();
            $this->run();
            $this->loop->run();
        };
    }

    private function createChannel(): void
    {
        // try to create signal channel.
        if (Process::isSupportPosixSignal()) {
            $this->signals = new SignalChannel(null, $this->loop, [
                \SIGTERM => new CloseCommand(false),
                \SIGHUP => new CloseCommand(true),
            ]);
            $this->signals->listen([$this, 'handleCommand']);
        } else {
            $this->logger->warning('Signal channel is not supported.');
        }
        $this->control = new UnixSocketChannel($this->sockets, $this->loop, $this->inChildProcess, CommandFactory::create());
        $this->control->listen([$this, 'handleCommand']);
    }

    private function handleCommand(CommandInterface $command): void
    {
        switch ($command->getCommandId()) {
            case 'CLOSE':
                $this->handleClose($command->isGraceful());
                break;
            case 'HEARTBEAT':
                $this->control->send(new PingCommand($this->getPid()));
                break;
            case 'CONTROL':
                if (($command->getFlags() & ControlCommand::CONNECTIONS) === ControlCommand::CONNECTIONS) {
                    $this->control->send(new ConnectionsCommand($this->getPid(), $this->connections));
                }
                if (($command->getFlags() & ControlCommand::STATUS) === ControlCommand::STATUS) {
                    $this->control->send(new WorkerStatusCommand($this->getPid(), $this->createStatus()));
                }
                break;
        }
    }

    private function handleClose(bool $grace): void
    {
        $this->requireInChildProcess();
        $this->logger->info('Receive close command.');
        if ($grace) {
            $this->loop->stop();
            return;
        }
        exit(0);
    }

    private function requireInChildProcess(): void
    {
        if (!$this->inChildProcess) {
            throw new RuntimeException('The action can only be executed in child process.');
        }
    }
}