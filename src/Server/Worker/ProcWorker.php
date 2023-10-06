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
use Waveman\Server\Exception\RuntimeException;

final class ProcWorker extends Worker
{
    /**
     * @var Process
     */
    private Process $process;

    /**
     * {@inheritdoc}
     */
    public function doStart(): void
    {
        $entry = self::getEntryFile();
        $this->process = Process::fromShellCommandline(sprintf("php %s --config %s", $entry));
        $this->process->start();
    }

    private static function getEntryFile(): string
    {
        global $argv;
        foreach($argv as $file) {
            if(\is_file($file)) {
                return $file;
            }
        }
        throw new RuntimeException('Cannot find entry file.');
    }

    /**
     * {@inheritdoc}
     */
    public function doClose(bool $graceful = false): void
    {
        $this->process->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function doAlive(): void
    {
        
    }
}