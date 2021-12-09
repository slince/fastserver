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

use FastServer\Bridge\BridgeFactory;
use FastServer\Bridge\Command\CLOSE;
use Psr\Log\LoggerInterface;
use React\EventLoop\ExtEventLoop;
use React\EventLoop\LoopInterface;
use React\Stream\CompositeStream;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use FastServer\Bridge\Command\CommandFactory;
use FastServer\Bridge\Command\CommandInterface;
use FastServer\Bridge\BridgeInterface;
use FastServer\Exception\RuntimeException;
use FastServer\Process\Process;
use FastServer\Bridge\Message;
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
     * @var BridgeInterface
     */
    protected $control;

    /**
     * @var bool
     */
    protected $isSupportSignal = false;

    /**
     * Whether in the child process.
     * @var bool
     */
    protected $inChildProcess = false;

    public function __construct(int $id, ServerInterface $server, LoopInterface $loop, LoggerInterface $logger)
    {
        parent::__construct($id, $server, $loop, $logger);
        $this->commands = $this->createCommandFactory();
        $this->isSupportSignal = Process::isSupportPosixSignal();
    }

    public function getPid(): int
    {
        return $this->process->getPid();
    }

    public function start()
    {
        $this->process = new Process($this->createCallable());
        $this->process->start(false);
        $this->control = BridgeFactory::createBridge(new CompositeStream(
            new ReadableResourceStream($this->process->stdout, $this->loop),
            new WritableResourceStream($this->process->stdin, $this->loop)
        ));
    }

    public function close(bool $grace = false)
    {
        // 如果支持信号，优先使用信号
        if ($this->isSupportSignal) {
            $this->process->signal($grace ? SIGHUP : SIGTERM);
        } else {
            $this->control->executeCommand(new CLOSE($grace));
        }
        parent::close();
    }

    protected function createCallable(): \Closure
    {
        return function($stdin, $stdout, $stderr){
            if ($this->loop instanceof ExtEventLoop) {
                $this->loop->getEventBase()->reInit();
            }
            $this->inChildProcess = true;

            $this->loop->addSignal(\SIGTERM, function(){
                $this->handleClose(false);
            });
            $this->loop->addSignal(\SIGHUP, function(){
                $this->handleClose(true);
            });

            $bridge = BridgeFactory::createBridge(new CompositeStream(
                new ReadableResourceStream($stdin, $this->loop),
                new WritableResourceStream($stdout, $this->loop)
            ));

            $this->listenCommands($bridge);

            $this->work();

            $this->loop->run();
        };
    }

    protected function listenCommands(BridgeInterface $bridge)
    {
        $bridge->listen(function(Message $message, BridgeInterface $bridge){
            $command = $this->commands->createCommand($message);
            $this->handleCommand($command, $bridge);
        });
    }

    protected function handleCommand(CommandInterface $command, BridgeInterface $bridge)
    {
        switch ($command->getCommandId()) {
            case 'CLOSE':
                $this->handleClose($command->isGraceful());
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
        $this->logger->info('Receive close command.');
        if ($grace) {
            $this->loop->stop();
        } else {
            exit(0);
        }
    }

    /**
     * Create command factory for the server.
     *
     * @return CommandFactory
     */
    protected function createCommandFactory(): CommandFactory
    {
        return new CommandFactory([
            'CLOSE' => CLOSE::class,
        ]);
    }
}