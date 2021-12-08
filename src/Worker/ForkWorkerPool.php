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
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

class ForkWorkerPool extends WorkerPool
{
    protected $index = 0;
    /**
     * {@inheritdoc}
     */
    public function createWorker(int $id, LoopInterface $loop, LoggerInterface $logger, ServerInterface $server)
    {
        return new ForkWorker($id, $loop, $logger, $server);
    }

    /**
     * {@inheritdoc}
     */
    public function wait()
    {
        $process = GlobalProcess::get();
        // grace close
        $process->signal([\SIGINT, \SIGTERM, \SIGQUIT, \SIGHUP, \SIGUSR1, SIGUSR2], [$this, 'onSignal'], false);
        $process->wait([$this, 'waitWorkers']);
    }

    /**
     * {@internal}
     */
    public function onSignal(int $signal)
    {
        switch ($signal) {
            case \SIGINT:
            case \SIGTERM:
            case \SIGQUIT:
                $this->close();
                break;
            case \SIGHUP:
                $this->close(true);
                break;
            case \SIGUSR1:
                $this->restart();
                break;
            case \SIGUSR2:
                $this->restart(true);
                break;
        }
    }

    public function close($graceful = false)
    {
        $this->status = self::STATUS_CLOSING;
        foreach ($this->workers as $worker) {
            $worker->close($graceful);
        }
    }

    public function restart($graceful = false)
    {
        $message = sprintf('Restart %d workers', count($this->workers)) . $graceful ? ' gracefully.' : '.';
        $this->logger->info($message);
        foreach ($this->workers as $worker) {
            $worker->close($graceful);
        }
    }

    public function waitWorkers(int $pid, StatusInfo $status)
    {
        if (-1 === $pid) {
            if ($this->index > 2) {
                var_dump('max index');
                exit;
            }
            $this->logger->info('Invalid signal.');
            var_dump($status->hasBeenSignaled(), $status->hasBeenStopped(), $status->hasBeenExited());
            var_dump($status->getStatusCode(), $status->getStopSignal(), $status->getTermSignal());
//            sleep(10000);
            $this->index ++;
            return;
        }
        var_dump('pid:' . $pid);
        $worker = $this->getWorker($pid);
        var_dump('worker null', is_null($worker));
        if (null === $worker) {
            $this->logger->info(sprintf('The worker[%d] is not found. and the pool has [%d] workers', $pid, $this->count()));
            return;
        }
        $this->remove($worker);
        if (self::STATUS_CLOSING === $this->status) {
            if (0 === count($this->workers)) {
                $this->logger->info('All workers has been exited, close the server.');
                $this->server->stop();
            }
            return;
        }
        $alternative = $this->createWorker($worker->getId(), $this->loop, $this->logger, $this->server);
        $this->add($alternative);
        $alternative->start();
        $this->logger->info(sprintf('The worker[%d] %d is exited and new one[%d] has been start', $pid, $worker->getId(), $alternative->getPid()));
    }
}