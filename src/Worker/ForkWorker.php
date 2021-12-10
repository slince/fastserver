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

use FastServer\Communicator\CommunicatorFactory;
use FastServer\Communicator\Command\CLOSE;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Stream\CompositeStream;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use FastServer\Communicator\Command\CommandFactory;
use FastServer\Communicator\Command\CommandInterface;
use FastServer\Communicator\CommunicatorInterface;
use FastServer\Exception\RuntimeException;
use FastServer\Process\Process;
use FastServer\Communicator\Message;
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
     * @var CommunicatorInterface
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

    /**
     * {@inheritdoc}
     */
    public function getPid(): int
    {
        return $this->process->getPid();
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->process = new Process($this->createCallable());
        $this->process->start(false);
        $this->control = CommunicatorFactory::createCommunicator(new CompositeStream(
            new ReadableResourceStream($this->process->stdout, $this->loop),
            new WritableResourceStream($this->process->stdin, $this->loop)
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function close(bool $grace = false)
    {
        $this->process->signal($grace ? \SIGHUP : \SIGTERM);
    }

    protected function createCallable(): \Closure
    {
        return function($stdin, $stdout, $stderr){
            // Reset loop instance.
            $this->loop = Loop::get();

            $this->inChildProcess = true;

            $this->loop->addSignal(\SIGTERM, function(){
                $this->handleClose(false);
            });
            $this->loop->addSignal(\SIGHUP, function(){
                $this->handleClose(true);
            });

            $communicator = CommunicatorFactory::createCommunicator(new CompositeStream(
                new ReadableResourceStream($stdin, $this->loop),
                new WritableResourceStream($stdout, $this->loop)
            ));

            $communicator->listen(function(Message $message, CommunicatorInterface $communicator){
                $command = $this->commands->createCommand($message);
                $this->handleCommand($command, $communicator);
            });

            $this->work();

            $this->loop->run();
        };
    }

    protected function handleCommand(CommandInterface $command, CommunicatorInterface $communicator)
    {
        switch ($command->getCommandId()) {
            case 'CLOSE':
                $this->handleClose($command->isGraceful());
                break;
        }
    }

    protected function handleClose(bool $grace)
    {
        $this->requireInChildProcess();
        $this->logger->info('Receive close command.');
        if ($grace) {
            $this->loop->stop();
        } else {
            exit(0);
        }
    }

    protected function createCommandFactory(): CommandFactory
    {
        return new CommandFactory([
            'CLOSE' => CLOSE::class,
        ]);
    }

    protected function requireInChildProcess()
    {
        if (!$this->inChildProcess) {
            throw new RuntimeException('The action can only be executed in child process.');
        }
    }
}