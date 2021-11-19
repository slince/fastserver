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
     * {@inheritdoc}
     */
    public function createWorker(int $id, LoopInterface $loop, ServerInterface $server)
    {
        return new ForkWorker($id, $loop, $server);
    }

    public function wait()
    {
        $process = GlobalProcess::get();
        pcntl_async_signals(true);
        $process->signal(SIGUSR1, function(){
            var_dump('收到信号');
        });
        $process->wait([$this, 'waitWorkers']);
    }

    public function waitWorkers(int $pid, StatusInfo $status)
    {
        if (-1 === $pid) {
            return;
        }
        $worker = $this->getWorker($pid);
        $this->remove($worker);
        $alternative = $this->createWorker($worker->getId(), $this->loop, $this->server);
        $this->add($alternative);
        $alternative->start();
        $this->logger->info(sprintf('The worker[%d] %d is exited and new one[%d] has been start', $pid, $worker->getId(), $alternative->getPid()));
    }
}