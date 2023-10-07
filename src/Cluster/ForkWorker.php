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

use React\EventLoop\Loop;
use Slince\Process\Process;
use Waveman\Channel\DelegatingChannel;
use Waveman\Channel\SignalChannel;
use Waveman\Channel\UnixSocketChannel;
use Waveman\Cluster\Command\NopCommand;
use Waveman\Server\Command\CloseCommand;
use Waveman\Server\Command\CommandFactory;
use Waveman\Server\Command\HeartbeatCommand;

final class ForkWorker extends Worker
{
    /**
     * @var Process
     */
    private Process $process;

    private ?SignalChannel $signals = null;

    private array $sockets;

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
    protected function doStart(): void
    {
        $this->sockets = UnixSocketChannel::createSocketPair();
        $this->process = new Process($this->createCallable());
        $this->process->start();
        // for master process
        $this->createChannel();
        $this->control->listen([$this, 'handleCommand']);
    }

    /**
     * {@inheritdoc}
     */
    protected function doClose(bool $graceful = false): void
    {
        $command = new CloseCommand($graceful);
        if (null !== $this->signals) {
            $this->signals->send($command);
        } else {
            $this->control->send($command);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doAlive(): void
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
            $this->signals->listen([$this, 'handleCommand']);
            $this->control->listen([$this, 'handleCommand']);
            $this->run();
            $this->logger->debug(sprintf('The worker %d is started', $this->getPid()));
            $this->loop->run();
        };
    }

    private function createChannel(): void
    {
        $loop = Loop::get();
        // try to create signal channel.
        $channels = [];
        if (Process::isSupportPosixSignal()) {
            $channels[] = new SignalChannel([
                \SIGTERM => new CloseCommand(true),
                \SIGQUIT => new CloseCommand(false),
                \SIGINT => new NopCommand(), // ignore ctrl+c
            ], $this->process, $loop);
        }
        $channels[] = new UnixSocketChannel($this->sockets, $loop, $this->inChildProcess, CommandFactory::create());
        $this->control = count($channels) > 1 ? new DelegatingChannel($channels) : $channels[0];
    }
}