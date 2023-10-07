<?php

namespace Waveman\Cluster;

final class ForkWorkerPool extends WorkerPool
{
    /**
     * {@inheritdoc}
     */
    public function create(int $id): Worker
    {
        return new ForkWorker($id);
    }

    /**
     * {@inheritdoc}
     */
    public function wait(bool $blocking = true): \Traversable
    {
        $pid = \pcntl_wait($status, $blocking ? \WUNTRACED : \WNOHANG | \WUNTRACED);
        if ($pid > 0) {
            $worker = $this->ensure($pid);
            $worker->terminate();
            yield $worker;
        }
    }
}