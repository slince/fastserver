<?php

namespace FastServer\Socket\Worker;

use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface as Socket;
use React\Stream\CompositeStream;
use React\Stream\DuplexResourceStream;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use FastServer\Connection\Command\CommandFactory;
use FastServer\Connection\ConnectionInterface;
use FastServer\Connection\StreamConnection;
use FastServer\Process\Process;
use FastServer\Process\ProcProcess;
use FastServer\Socket\ServerInterface;
use FastServer\Socket\Worker;

class ProcWorker extends Worker
{
    /**
     * @var CommandFactory
     */
    protected $commands;

    /**
     * @var ProcProcess
     */
    protected $process;

    /**
     * @var ConnectionInterface
     */
    protected $control;

    /**
     * @var bool
     */
    protected $isSupportSignal = false;

    protected $inChildProcess = false;

    public function __construct(LoopInterface $loop, ServerInterface $server)
    {
        parent::__construct($loop, $server);
        $this->commands = $this->createCommandFactory();
        $this->isSupportSignal = Process::isSupportPosixSignal();
    }

    public function start()
    {
        $config = [
            'address' => $this->server->getOption('address')
        ];
        $entryFile = __DIR__ . '/Internal/worker.php';
        $this->process = new ProcProcess(sprintf("php %s --configuration %s", $entryFile, json_encode($config)));
        $this->process->start(false);
        $this->control = new StreamConnection(new CompositeStream(
            new ReadableResourceStream($this->process->stdout, $this->loop),
            new WritableResourceStream($this->process->stdin, $this->loop),
        ));
    }

    /**
     * Create command factory for the server.
     *
     * @return CommandFactory
     */
    protected function createCommandFactory(): CommandFactory
    {
        return new CommandFactory([
            'CLOSE' => Command\CLOSE::class,
        ]);
    }
}