<?php

namespace FastServer\Socket\Worker;

use parallel\Runtime as ParallelRuntime;
use React\EventLoop\LoopInterface;
use FastServer\Socket\ServerInterface;
use FastServer\Socket\Worker;
use FastServer\Socket\WorkerPool;

class ParallelWorkerPool extends WorkerPool
{
    protected $runtime;

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->runtime = new ParallelRuntime();
        parent::run();
    }


    /**
     * {@inheritdoc}
     */
    public function createWorker(LoopInterface $loop, ServerInterface $server)
    {
        return new Worker($loop, $server);
    }
}