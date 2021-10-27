<?php

declare(strict_types=1);

/*
 * This file is part of the fastserver/fastserver package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FastServer\Worker;

use React\EventLoop\LoopInterface;
use React\Stream\CompositeStream;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use FastServer\Connection\Command\CommandFactory;
use FastServer\Connection\Command\CommandInterface;
use FastServer\Connection\ConnectionInterface;
use FastServer\Connection\StreamConnection;
use FastServer\Exception\RuntimeException;
use FastServer\Process\Process;
use FastServer\Connection\Message;
use FastServer\Connection\MessageParser;
use FastServer\ServerInterface;

class ForkWorker extends Worker
{
    /**
     * @var CommandFactory
     */
    protected $commands;

    /**
     * @var Process
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
        $this->process = new Process($this->createCallable());
        if ($this->isSupportSignal) {
            $this->registerSignals();
        }
        $this->process->start(false);
        $this->control = new StreamConnection(new CompositeStream(
            new ReadableResourceStream($this->process->stdout, $this->loop),
            new WritableResourceStream($this->process->stdin, $this->loop),
        ));
    }

    public function close(bool $grace = false)
    {
        // 如果支持信号，优先使用信号
        if ($this->isSupportSignal) {
            $this->process->signal($grace ? SIGHUP : SIGTERM);
        } else {
            $this->control->executeCommand(new Command\CLOSE($grace));
        }
        parent::close();
    }

    protected function registerSignals()
    {
        foreach ($this->signals as $signal => $handler) {
            $this->process->registerSignal($signal, $handler);
        }
        $this->process->registerSignal([SIGINT, SIGTERM], function(){
            $this->handleClose(false);
        });
        $this->process->registerSignal([SIGHUP], function(){
            $this->handleClose(true);
        });
    }

    public function createCallable(): \Closure
    {
        return function($stdin, $stdout, $stderr){
            $this->inChildProcess = true;

            $connection = new StreamConnection(new CompositeStream(
                new ReadableResourceStream($stdin, $this->loop),
                new WritableResourceStream($stdout, $this->loop)
            ));

            $this->listenCommands($connection);

            $this->work();

            $this->loop->run();
        };
    }

    protected function listenCommands(ConnectionInterface $connection)
    {
        $connection->on('message', function(Message $message, ConnectionInterface $connection){
            $command = $this->commands->createCommand($message);
            $this->handleCommand($command, $connection);
        });

        $parser = new MessageParser($connection);
        $parser->parse();
    }

    protected function handleCommand(CommandInterface $command, ConnectionInterface $connection)
    {
        switch ($command->getCommandId()) {
            case 'CLOSE':
                $this->handleClose($command->isGrace());
                break;
        }
    }

    /**
     * {@internal}
     */
    public function handleClose(bool $grace)
    {
        if (!$this->inChildProcess) {
            throw new RuntimeException('The action can only be executed in child process.');
        }
        if ($grace) {
            $this->loop->stop();
        }
        exit(0);
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