<?php

namespace FastServer\Timer;

use FastServer\AbstractServer;

class WorkerWatcherTimer
{
    /**
     * @var AbstractServer
     */
    protected $server;

    public function __construct(AbstractServer $server)
    {
        $this->server = $server;
    }

    public function __invoke()
    {
        foreach ($this->server->getPool() as $worker) {

        }
    }
}