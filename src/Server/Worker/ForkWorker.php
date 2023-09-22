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

namespace Waveman\Server\Worker;

use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Stream\CompositeStream;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use Slince\Process\Process;
use Waveman\Server\Channel\ChannelFactory;
use Waveman\Server\Channel\ChannelInterface;
use Waveman\Server\Channel\Command\CommandFactory;
use Waveman\Server\ServerInterface;

class ForkWorker extends Worker
{
    /**
     * @var CommandFactory
     */
    protected CommandFactory $commands;

    /**
     * @var Process
     */
    protected Process $process;

    /**
     * @var ChannelInterface
     */
    protected ChannelInterface $control;

    /**
     * @var bool
     */
    protected bool $isSupportSignal = false;

    /**
     * Whether in the child process.
     * @var bool
     */
    protected bool $inChildProcess = false;

    public function __construct(int $id, ServerInterface $server, LoggerInterface $logger, LoopInterface $loop)
    {
        parent::__construct($id, $server, $logger, $loop);
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
    public function start(): void
    {
        $this->process = new Process($this->createCallable());
        $this->process->start();
        $this->control = ChannelFactory::createChannel(new CompositeStream(
            new ReadableResourceStream($this->process->stdout, $this->loop),
            new WritableResourceStream($this->process->stdin, $this->loop)
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function close(bool $grace = false): void
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