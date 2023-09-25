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
use Slince\Process\Process;
use Waveman\Server\Channel\ChannelFactory;
use Waveman\Server\Channel\ChannelInterface;
use Waveman\Server\Channel\Command\CLOSE;
use Waveman\Server\Channel\Command\CommandInterface;
use Waveman\Server\Channel\CommandFactory;
use Waveman\Server\Channel\SignalChannel;
use Waveman\Server\Channel\UnixSocketChannel;
use Waveman\Server\Exception\RuntimeException;
use Waveman\Server\ServerInterface;

class ForkWorker extends Worker
{
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
        $this->control = new UnixSocketChannel($this->loop, false, $this->createCommandFactory());
        $this->process->start();
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
        return function(){
            // Reset loop instance.
            $this->loop = Loop::get();

            $this->inChildProcess = true;

            $channel = ChannelFactory::createStreamChannel();

            $channel->listen(function(CommandInterface $command){
                $this->handleCommand($command);
            });

            $this->loop->run();
        };
    }

    private function createChannel(LoopInterface $loop): ChannelInterface
    {
        try {
            $channel = new SignalChannel(null, $loop, [
                \SIGTERM => new CLOSE(false),
                \SIGHUP => new CLOSE(true),
            ]);
        } catch (\Exception $exception) {
            $this->logger->warning(sprintf('Signal channel is not supported, error: %s.', $exception->getMessage()));
            $channel = new UnixSocketChannel($this->loop, $this->inChildProcess, self::createCommandFactory());
        }
        return $channel;
    }

    protected function handleCommand(CommandInterface $command): void
    {
        switch ($command->getCommandId()) {
            case 'CLOSE':
                $this->handleClose($command->isGraceful());
                break;
        }
    }

    protected function handleClose(bool $grace): void
    {
        $this->requireInChildProcess();
        $this->logger->info('Receive close command.');
        if ($grace) {
            $this->loop->stop();
            return;
        }
        exit(0);
    }

    protected static function createCommandFactory(): CommandFactory
    {
        return new CommandFactory([CLOSE::class]);
    }

    protected function requireInChildProcess(): void
    {
        if (!$this->inChildProcess) {
            throw new RuntimeException('The action can only be executed in child process.');
        }
    }
}