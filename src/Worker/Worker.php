<?php

namespace FastServer\Worker;

use FastServer\Process\Process;
use FastServer\Process\ProcessInterface;
use FastServer\ServerInterface;

class Worker implements WorkerInterface
{
    /**
     * @var ServerInterface
     */
    protected $server;

    /**
     * @var resource
     */
    protected $socket;

    /**
     * @var ProcessInterface
     */
    protected $process;

    public function __construct(ServerInterface $server, $socket)
    {
        $this->server = $server;
        $this->socket = $socket;
        $this->process = new Process([$this, 'work']);
    }

    public function start()
    {
        $this->process->start();
    }

    public function stop()
    {
        $this->process->stop();
    }

    abstract protected function createRelay();

    abstract  public function work();
}