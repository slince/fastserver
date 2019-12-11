<?php

namespace FastServer\Worker;

use FastServer\Process\Process;
use FastServer\Process\ProcessInterface;
use FastServer\Relay\RelayInterface;
use FastServer\ServerInterface;
use React\Socket\ServerInterface as Socket;

class Worker implements WorkerInterface
{
    /**
     * @var ServerInterface
     */
    protected $server;

    /**
     * @var Socket
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

    public function __construct(ServerInterface $server, Socket $socket)
    {
        $this->server = $server;
        $this->socket = $socket;
        $this->process = new Process([$this, 'work']);
    }

    public function start()
    {
        $this->initialize();
        $this->process->start();
    }

    public function stop()
    {
        $this->process->stop();
    }

    /**
     * @param int $signal
     * @param callable $handler
     */
    public function onSignal($signal, $handler)
    {
        $this->process->onSignal($signal, $handler);
    }

    public function getPid()
    {
        return $this->process->getPid();
    }

    protected function initialize()
    {
        $this->onSignal(SIGTERM, function(){
        });
    }

    /**
     * @internal
     */
     public function work()
     {
        $this->socket->on('connection', [$this->server, 'handleConnection']);
     }
}