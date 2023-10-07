<?php

namespace Waveman\Cluster;

final class ProcWorkerPool extends WorkerPool
{
    /**
     * {@inheritdoc}
     */
    public function create(int $id, Cluster $cluster): Worker
    {
        return new ProcWorker($id, $cluster);
    }

    /**
     * {@inheritdoc}
     */
    public function wait(bool $blocking = true): \Traversable
    {
        do {
            /* @var ProcWorker $worker */
            foreach ($this->workers as $worker) {
                $process = $worker->getProcess();
                if ($process->isTerminated()) {
                    $worker->terminate();
                    yield $worker;
                }
            }
            usleep(2000);
        } while($blocking);
    }
}