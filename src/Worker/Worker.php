<?php

namespace FastServer\Worker;

use FastServer\Process\Process;
use FastServer\Process\ProcessInterface;
use FastServer\Relay\RelayInterface;
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

    /**
     * @var RelayInterface
     */
    protected $relay;

    public function __construct(ServerInterface $server, $socket)
    {
        $this->server = $server;
        $this->socket = $socket;
        $this->process = new Process([$this, 'work']);
        $this->relay = $this->createRelay();
    }

    public function start()
    {
        $this->process->start();
    }

    public function stop()
    {
        $this->process->stop();
    }

    public function send($payload, int $flags = null)
    {
        $this->relay->send(json_encode($payload), $flags);
    }

    abstract protected function createRelay();

    /**
     * @internal
     */
    abstract  public function work();
}