<?php

namespace FastServer\Worker;

use FastServer\Relay\AsyncStreamRelay;

class AsyncWorker extends AbstractWorker
{
    public function send($payload)
    {

    }

    public function work()
    {
        
    }

    protected function createRelay()
    {
        return new AsyncStreamRelay($this->process->getInput(), $this->process->getOutput());
    }
}