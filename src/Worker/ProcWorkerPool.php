<?php

namespace FastServer\Socket\Worker;

use React\EventLoop\LoopInterface;
use FastServer\Socket\ServerInterface;
use FastServer\Socket\WorkerPool;

class ProcWorkerPool extends WorkerPool
{
    /**
     * {@inheritdoc}
     */
    public function createWorker(LoopInterface $loop, ServerInterface $server)
    {
        return new ProcWorker($loop, $server);
    }
}