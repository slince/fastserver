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

namespace Waveman\Cluster;

use React\EventLoop\Loop;
use React\Stream\WritableResourceStream;
use Slince\Process\Process;
use Symfony\Component\Process\PhpProcess;
use Symfony\Component\Process\Process as SymfonyProcess;
use Waveman\Channel\DelegatingChannel;
use Waveman\Channel\SignalChannel;
use Waveman\Channel\StreamChannel;
use Waveman\Cluster\Command\CommandFactory;
use Waveman\Cluster\Command\NopCommand;
use Waveman\Server\Command\CloseCommand;
use Waveman\Server\Exception\RuntimeException;

final class ProcWorker extends Worker
{
    /**
     * @var SymfonyProcess
     */
    private SymfonyProcess $process;

    /**
     * {@inheritdoc}
     */
    public function getPid(): int
    {
        return $this->process->getPid();
    }

    /**
     * @return SymfonyProcess
     */
    public function getProcess(): SymfonyProcess
    {
        return $this->process;
    }

    /**
     * {@inheritdoc}
     */
    public function doStart(): void
    {
        $entry = self::getEntryFile();
        $this->process = new PhpProcess($entry, null, [Cluster::WAVE_MAN_PID => $this->getPid()], 0);
        $stream = fopen('php://temporary', 'w+');
        $this->process->setInput($stream);
        $this->createChannel($stream);
        $this->process->start();
    }

    private function createChannel($stream): void
    {
        $loop = Loop::get();
        // try to create signal channel.
        $channels = [];
        if (Process::isSupportPosixSignal()) {
            $channels[] = new SignalChannel([
                \SIGTERM => new CloseCommand(true),
                \SIGQUIT => new CloseCommand(false),
                \SIGINT => new NopCommand(), // ignore ctrl+c
            ], $this->process, $loop);
        }
        $channels[] = new StreamChannel(new WritableResourceStream($stream), CommandFactory::create());
        $this->control = count($channels) > 1 ? new DelegatingChannel($channels) : $channels[0];
    }

    private static function getEntryFile(): string
    {
        $filename = $_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'];
        if (empty($filename)) {
            throw new RuntimeException('Cannot find entry file.');
        }
        return $filename;
    }
}