<?php

namespace FastServer\Worker;

use Amp\Loop;
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
        Loop::run(function () {
            $stream = $this->process->getInput();
            stream_set_blocking($stream, false);
            Loop::onReadable($stream, function(){

            });
        });
    }
}