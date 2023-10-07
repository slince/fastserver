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

use Symfony\Component\Process\PhpProcess;
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
        $this->process = new PhpProcess($entry, null, [Cluster::WAVE_MAN_NAME => $this->getPid()], 0);
        $this->process->start();
    }

    private static function getEntryFile(): string
    {
        $filename = $_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'];
        if (empty($filename)) {
            throw new RuntimeException('Cannot find entry file.');
        }
        return $filename;
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