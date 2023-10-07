<?php

namespace Waveman\Cluster;

final class ForkWorkerPool extends WorkerPool
{
    private $callback;

    public function __construct(Cluster $cluster, callable $callback)
    {
        parent::__construct($cluster);
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function create(int $id): Worker
    {
        return new ForkWorker($id, $this->cluster, $this->callback);
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