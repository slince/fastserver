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

namespace FastServer\Worker;

use FastServer\Process\GlobalProcess;
use FastServer\Process\StatusInfo;
use FastServer\ServerInterface;
use React\EventLoop\LoopInterface;

class ForkWorkerPool extends WorkerPool
{
    /**
     * Whether the pool is closing.
     *
     * @var bool
     */
    protected $closing = false;

    /**
     * {@inheritdoc}
     */
    public function createWorker(int $id, LoopInterface $loop, ServerInterface $server)
    {
        return new ForkWorker($id, $loop, $server);
    }

    public function wait()
    {
        $process = GlobalProcess::get();

        // grace close
        $process->signal(\SIGHUP, [$this, 'onSignal'], false);
        $process->signal(\SIGINT, [$this, 'onSignal'], false);
        $process->signal(\SIGTERM, [$this, 'onSignal'], false);
        $process->signal(\SIGQUIT, [$this, 'onSignal'], false);
        $process->signal(\SIGUSR1, [$this, 'onSignal'], false);

        $process->wait([$this, 'waitWorkers']);
    }

    /**
     * {@internal}
     */
    public function onSignal(int $signal)
    {
        switch ($signal) {
            case \SIGHUP:
                $this->close(true);
                break;
            case \SIGINT:
            case \SIGTERM:
                $this->close();
                break;
            case SIGQUIT:
                $this->restart();
                break;
            case \SIGUSR1:
                $this->restart(true);
                break;
        }
    }

    public function close($grace = false)
    {
        $this->closing = true;
        foreach ($this->workers as $worker) {
            $worker->close($grace);
        }
    }

    public function restart($grace = false)
    {
        foreach ($this->workers as $worker) {
            $worker->close($grace);
        }
    }

    public function waitWorkers(int $pid, StatusInfo $status)
    {
        var_dump($pid, $status->hasBeenExited(), $status->hasBeenStopped(), $status->hasBeenSignaled());
        if (-1 === $pid) {
            return;
        }
        $worker = $this->getWorker($pid);
        $this->remove($worker);
        if ($this->closing) {
            return;
        }
        $alternative = $this->createWorker($worker->getId(), $this->loop, $this->server);
        $this->add($alternative);
        $alternative->start();
        $this->logger->info(sprintf('The worker[%d] %d is exited and new one[%d] has been start', $pid, $worker->getId(), $alternative->getPid()));
    }
}