<?php

namespace Waveman\Cluster;

final class ProcWorkerPool extends WorkerPool
{
    /**
     * {@inheritdoc}
     */
    public function create(int $id): Worker
    {
        return new ProcWorker($id);
    }

    /**
     * {@inheritdoc}
     */
    public function wait(bool $blocking = true): ?Worker
    {
        return null;
    }
}