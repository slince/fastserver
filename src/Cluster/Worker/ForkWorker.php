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

namespace Viso\Cluster\Worker;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;
use Slince\Process\Process;
use Viso\Channel\StreamChannel;
use Viso\Cluster\Command\CommandFactory;
use Viso\Cluster\SignalUtils;
use Viso\Server\Exception\RuntimeException;

final class ForkWorker extends Worker
{
    /**
     * @var Process
     */
    private Process $process;

    private array $sockets;

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
    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    protected function doClose(): void
    {
        $this->process->signal(\SIGKILL);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSignal(int $signal): void
    {
        $this->process->signal($signal);
    }

    /**
     * {@inheritdoc}
     */
    protected function doStart(): void
    {
        $this->sockets = self::createSocketPair();
        $this->process = new Process($this->createCallable());
        $this->process->start();
        // for master process
        $this->createChannel();
        $this->control->listen([$this, 'handleCommand']);
    }

    private function createCallable(): \Closure
    {
        return function(){
            $this->cluster->isPrimary = false;
            $this->cluster->worker = $this;
            SignalUtils::registerSignals($this->cluster->getSignals(), \SIG_IGN);
            $this->createChannel(Loop::get());
            $this->run();
        };
    }

    private function createChannel(LoopInterface $loop): void
    {
        // try to create signal channel.
        $this->control = new StreamChannel(self::createStream($this->sockets, $this->cluster->isPrimary), CommandFactory::create());
        $this->control->listen([$this, 'handleCommand']);
    }

    private static function createStream(array $sockets, bool $primary): DuplexResourceStream
    {
        if ($primary) {
            fclose($sockets[0]);
            $stream = $sockets[1];
        } else {
            fclose($sockets[1]);
            $stream = $sockets[0];
        }
        return new DuplexResourceStream($stream, $loop);
    }


    private static function createSocketPair(): array
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            throw new RuntimeException('Cannot create socket pairs.');
        }
        return $sockets;
    }
}