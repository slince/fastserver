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

use Symfony\Component\Process\Process;
use Waveman\Server\Channel\ChannelInterface;

class ProcWorker extends Worker
{
    /**
     * @var Process
     */
    private Process $process;

    /**
     * @var ChannelInterface
     */
    private ChannelInterface $control;

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        $config = [
            'address' => $this->server->getOption('address')
        ];
        $entry = __DIR__ . '/Internal/worker.php';
        $this->process = Process::fromShellCommandline(sprintf("php %s --config %s", $entry, json_encode($config)));
        $this->process->start();
    }

    /**
     * {@inheritdoc}
     */
    public function close(bool $graceful = false): void
    {
        $this->process->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function alive(): void
    {
        
    }

    public function handleClose(bool $grace): void
    {
        $this->process->stop();
    }

    public function getControl(): ChannelInterface
    {
        return $this->control;
    }
}