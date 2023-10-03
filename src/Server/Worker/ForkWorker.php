<?php

declare(strict_types=1);

/*
 * This file is part of the waveman/waveman package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Waveman\Server\Worker;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Slince\Process\Process;
use Waveman\Server\Channel\ChannelInterface;
use Waveman\Server\Channel\CommandInterface;
use Waveman\Server\Channel\SignalChannel;
use Waveman\Server\Channel\UnixSocketChannel;
use Waveman\Server\Command\CloseCommand;
use Waveman\Server\Command\CommandFactory;
use Waveman\Server\Exception\RuntimeException;
use Waveman\Server\Server;

final class ForkWorker extends Worker
{
    /**
     * @var Process
     */
    private Process $process;

    /**
     * @var ChannelInterface
     */
    private ChannelInterface $control;

    private ?array $sockets = null;

    /**
     * @var bool
     */
    private bool $isSupportSignal;

    /**
     * Whether in the child process.
     * @var bool
     */
    private bool $inChildProcess = false;

    public function __construct(int $id, Server $server)
    {
        parent::__construct($id, $server);
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
        if (!$this->isSupportSignal) {
            $this->sockets = UnixSocketChannel::createSocketPair();
        }
        $this->process = new Process($this->createCallable());
        $this->control = $this->createChannel($this->loop);
        $this->process->start();
    }

    /**
     * {@inheritdoc}
     */
    public function close(bool $graceful = false): void
    {
        $this->control->send(new CloseCommand($graceful));
    }

    private function createCallable(): \Closure
    {
        return function(){
            // Reset loop instance.
            $this->loop = Factory::create();

            $this->inChildProcess = true;

            $channel = $this->createChannel($this->loop);

            $channel->listen(function(CommandInterface $command){
                $this->handleCommand($command);
            });

            $this->run();
            $this->loop->run();
        };
    }

    private function createChannel(LoopInterface $loop): ChannelInterface
    {
        if ($this->isSupportSignal) {
            $channel = new SignalChannel(null, $loop, [
                \SIGTERM => new CloseCommand(false),
                \SIGHUP => new CloseCommand(true),
            ]);
        } else {
            $this->logger->warning('Signal channel is not supported.');
            $channel = new UnixSocketChannel($this->sockets, $this->loop, $this->inChildProcess, self::createCommandFactory());
        }
        return $channel;
    }

    private function handleCommand(CommandInterface $command): void
    {
        switch ($command->getCommandId()) {
            case 'CLOSE':
                $this->handleClose($command->isGraceful());
                break;
        }
    }

    private function handleClose(bool $grace): void
    {
        $this->requireInChildProcess();
        $this->logger->info('Receive close command.');
        if ($grace) {
            $this->loop->stop();
            return;
        }
        exit(0);
    }

    private static function createCommandFactory(): CommandFactory
    {
        return new CommandFactory();
    }

    private function requireInChildProcess(): void
    {
        if (!$this->inChildProcess) {
            throw new RuntimeException('The action can only be executed in child process.');
        }
    }
}