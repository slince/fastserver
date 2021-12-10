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

use FastServer\Process\StatusInfo;

class ForkWorkerPool extends WorkerPool
{
    protected $index = 0;

    /**
     * {@inheritdoc}
     */
    public function createWorker(int $id): Worker
    {
        return new ForkWorker($id, $this->server, $this->logger, $this->loop);
    }

    /**
     * {@inheritdoc}
     */
    public function close($graceful = false)
    {
        $this->status = self::STATUS_CLOSING;
        $this->closeWorkers($graceful);
    }

    /**
     * {@inheritdoc}
     */
    public function restart($graceful = false)
    {
        $message = sprintf('Restart %d workers', count($this->workers)) . $graceful ? ' gracefully.' : '.';
        $this->logger->info($message);
        $this->closeWorkers($graceful);
    }

    public function removeWorker(int $pid)
    {
        if (-1 === $pid) {
            return;
        }
        $worker = $this->getWorker($pid);
        if (null === $worker) {
            $this->logger->info(sprintf('The worker[%d] is not found. and the pool has [%d] workers', $pid, $this->count()));
            return;
        }
        $this->remove($worker);
        if (self::STATUS_CLOSING === $this->status) {
            $this->watchClosing();
            return;
        }
        $this->createAlternative($worker);
    }

    protected function watchClosing()
    {
        if (0 === count($this->workers)) {
            $this->logger->info('All workers has been exited, close the server.');
            $this->server->quit();
        }
    }

    protected function createAlternative(Worker $original)
    {
        $alternative = $this->createWorker($original->getId());
        $this->add($alternative);
        $alternative->start();
        $this->logger->info(sprintf('The worker[%d] %d is exited and new one[%d] has been start', $original->getPid(), $original->getId(), $alternative->getPid()));
    }
}