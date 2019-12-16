<?php

namespace FastServer;

use FastServer\Process\FakeProcess;
use FastServer\Process\Process;
use FastServer\Process\ProcessInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface as Socket;

final class Worker
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
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var callable[]
     */
    protected $signals;

    public function __construct(ServerInterface $server, LoopInterface $loop, Socket $socket)
    {
        $this->server = $server;
        $this->loop = $loop;
        $this->socket = $socket;
    }

    /**
     * Starts the worker.
     */
    public function start()
    {
        $this->process = static::createProcess([$this, 'work']);
        $this->initialize();
        $this->process->start(false);
    }

    /**
     * Stop the worker.
     */
    public function stop()
    {
        $this->process->stop();
    }

    /**
     * Register signal handler.
     *
     * @param $signal
     * @param callable $handler
     */
    public function onSignal($signal, $handler)
    {
        $this->signals[$signal] = $handler;
    }

    /**
     * Gets the worker pid.
     *
     * @return int
     */
    public function getPid()
    {
        return $this->process->getPid();
    }

    /**
     * Close the worker.
     *
     * {@internal }
     */
    public function close()
    {
        $this->loop->stop();
    }

    protected function initialize()
    {
        $this->onSignal(SIGTERM, [$this, 'close']);
        $this->onSignal(SIGUSR1, [$this, 'retry']);
    }

    protected static function createProcess(callable $callback)
    {
        if (function_exists('pcntl_fork')) {
            return new Process($callback);
        }
        return new FakeProcess($callback);
    }

    /**
     * @internal
     */
     public function work()
     {
         foreach ($this->signals as $signal => $handler) {
             $this->loop->addSignal($signal, $handler);
         }
        $this->socket->on('connection', [$this->server, 'handleConnection']);
        $this->loop->run();
     }
}