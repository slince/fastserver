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

use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use Symfony\Component\Process\PhpProcess;
use Symfony\Component\Process\Process as SymfonyProcess;
use Viso\Channel\StreamChannel;
use Viso\Cluster\Cluster;
use Viso\Cluster\Command\CommandFactory;
use Viso\Cluster\Exception\RuntimeException;

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
    protected function doRun(): void
    {
        $this->control = new StreamChannel(new ReadableResourceStream(STDIN), CommandFactory::create());
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
        $this->control = new StreamChannel(new WritableResourceStream($stream), CommandFactory::create());
        $this->process->start();
    }

    /**
     * {@inheritdoc}
     */
    protected function doClose(): void
    {
        $this->process->stop(10,\SIGKILL);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSignal(int $signal): void
    {
        $this->process->signal($signal);
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