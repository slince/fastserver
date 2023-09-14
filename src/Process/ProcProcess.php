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

namespace Wave\Process;

use \Symfony\Component\Process\Process as BaseProcess;
final class ProcProcess implements ProcessInterface
{

    public function run(bool $blocking = true): void
    {
        // TODO: Implement run() method.
    }

    public function wait()
    {
        // TODO: Implement wait() method.
    }

    public function close()
    {
        // TODO: Implement close() method.
    }

    public function terminate(int $signal = null)
    {
        // TODO: Implement terminate() method.
    }

    public function signal(int $signal)
    {
        // TODO: Implement signal() method.
    }

    public function getPid(): ?int
    {
        // TODO: Implement getPid() method.
    }

    public function getStdin()
    {
        // TODO: Implement getStdin() method.
    }

    public function getStdout()
    {
        // TODO: Implement getStdout() method.
    }

    public function getStderr()
    {
        // TODO: Implement getStderr() method.
    }

    public function isRunning(): bool
    {
        // TODO: Implement isRunning() method.
    }

    public function isStarted(): bool
    {
        // TODO: Implement isStarted() method.
    }

    public function isTerminated(): bool
    {
        // TODO: Implement isTerminated() method.
    }

    public function getStatus(): string
    {
        // TODO: Implement getStatus() method.
    }

    public function getExitCode(): ?int
    {
        // TODO: Implement getExitCode() method.
    }
}