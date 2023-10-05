<?php

namespace Waveman\Server\Worker;

final class ProcWorkerPool extends WorkerPool
{
    /**
     * {@inheritdoc}
     */
    public function create(int $id): Worker
    {
        return new ProcWorker($id, $this->server);
    }

    /**
     * {@inheritdoc}
     */
    public function wait(bool $blocking = true): ?Worker
    {
        return null;
    }
}