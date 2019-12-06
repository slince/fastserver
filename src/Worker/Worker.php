<?php

namespace FastServer\Worker;

use FastServer\Process\Process;
use FastServer\Process\ProcessInterface;

class Worker implements WorkerInterface
{
    /**
     * @var ProcessInterface
     */
    protected $process;

    public function __construct()
    {
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

    public function work()
    {
    }
}