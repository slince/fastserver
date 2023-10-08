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

use React\EventLoop\Factory;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Slince\Process\Process;
use Waveman\Channel\UnixSocketChannel;
use Waveman\Server\Command\CommandFactory;

final class ForkWorker extends Worker
{
    /**
     * @var Process
     */
    private Process $process;

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
    protected function doClose(): void
    {
        $this->process->signal(\SIGKILL);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSignal(int $signal): void
    {
        $this->process->signal($signal);
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
        $this->createChannel(Loop::get());
        $this->control->listen([$this, 'handleCommand']);
    }

    private function createCallable(): \Closure
    {
        return function(){
            $this->cluster->isPrimary = false;
            $this->cluster->worker = $this;
            // reset loop instance.
            $this->stopLoop(Loop::get());
            $loop = Factory::create();
            $this->createChannel($loop);
            $this->run();
        };
    }

    private function stopLoop(LoopInterface $loop): void
    {
        $loop->stop();
    }

    private function createChannel(LoopInterface $loop): void
    {
        // try to create signal channel.
        $this->control = new UnixSocketChannel($this->sockets, $loop, !$this->cluster->isPrimary, CommandFactory::create());
        $this->control->listen([$this, 'handleCommand']);
    }
}