<?php

namespace Waveman\Server\Worker;

final class ForkWorkerPool extends WorkerPool
{
    /**
     * {@inheritdoc}
     */
    public function create(int $id): Worker
    {
        return new ForkWorker($id, $this->server);
    }

    /**
     * {@inheritdoc}
     */
    public function wait(bool $blocking = true): ?Worker
    {
        $pid = \pcntl_wait($status, $blocking ? \WUNTRACED : \WNOHANG | \WUNTRACED);
        if ($pid > 0) {
            $worker = $this->ensure($pid);
            $worker->terminate();
            return $worker;
        }
        return null;
    }
}