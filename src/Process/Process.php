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

use Wave\Process\Exception\LogicException;
use Wave\Process\Exception\RuntimeException;
use Wave\Process\Fork\CurrentProcess;
use Wave\Process\Fork\Fifo;

class Process extends AbstractProcess
{
    /**
     * @var \Closure
     */
    protected \Closure $callback;

    /**
     * pid
     * @var int
     */
    protected int $pid;

    protected int $statusInfo;

    protected int $exitCode;

    protected Fifo $stdinFifo;
    protected Fifo $stdoutFifo;
    protected Fifo $stderrFifo;

    protected static CurrentProcess $currentProcess;

    public function __construct(callable $callback)
    {
        if (!function_exists('pcntl_fork')) {
            throw new RuntimeException('The Process class relies on ext-pcntl, which is not available on your PHP installation.');
        }
        if ($callback instanceof \Closure) {
            \Closure::bind($callback, null);
        }
        $this->callback = $callback;
        $this->stdinFifo = $this->createFifo();
        $this->stdoutFifo = $this->createFifo();
        $this->stderrFifo = $this->createFifo();
    }

    protected function createFifo(string $suffix = null): Fifo
    {
        return new Fifo(sys_get_temp_dir() . '/sl_' . mt_rand(0, 999) . $suffix . '.pipe');
    }

    /**
     * Returns the current process instance.
     * @return CurrentProcess
     */
    public static function current(): CurrentProcess
    {
        if (null === self::$currentProcess) {
            self::$currentProcess = new CurrentProcess();
        }
        return self::$currentProcess;
    }

    /**
     * Checks whether support signal.
     * @return bool
     */
    public static function isSupportPosixSignal(): bool
    {
        return function_exists('pcntl_signal');
    }

    /**
     * {@inheritdoc}
     */
    public function start(bool $blocking = true): void
    {
        if ($this->isRunning()) {
            throw new RuntimeException("The process is already running");
        }
        $pid = \pcntl_fork();
        if ($pid == -1) {
            throw new RuntimeException("Could not fork");
        } elseif ($pid) { //Records the pid of the child process
            $this->pid = $pid;
            $this->status = self::STATUS_STARTED;
            $this->stdin = $this->stdinFifo->open('w');
            $this->stdout = $this->stdoutFifo->open('r');
            $this->stderr = $this->stderrFifo->open('r');
            $this->updateStatus($blocking);
        } else {
            $stdin = $this->stdinFifo->open('r');
            $stdout = $this->stdoutFifo->open('w');
            $stderr = $this->stderrFifo->open('w');
            try {
                $exitCode = call_user_func($this->callback, $stdin, $stdout, $stderr);
            } catch (\Exception $e) {
                $exitCode  = 255;
            }
            exit(intval($exitCode));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function wait(): void
    {
        $this->isRunning() && $this->updateStatus(true);
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        $this->signal(SIGKILL);
        $this->status = self::STATUS_TERMINATED;
    }

    /**
     * {@inheritdoc}
     */
    public function getPid(): int
    {
        return $this->pid;
    }

    /**
     * {@inheritdoc}
     */
    public function signal(int $signal): void
    {
        if (!$this->running) {
            throw new RuntimeException("The process is not currently running");
        }
        posix_kill($this->getPid(), $signal);
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        //if process is not running, return false
        if (self::STATUS_STARTED !== $this->status) {
            return false;
        }
        //if the process is running, update process status again
        $this->updateStatus(false);
        return self::STATUS_STARTED === $this->status;
    }

    /**
     * {@inheritdoc}
     */
    protected function updateStatus(bool $blocking): void
    {
        if (self::STATUS_STARTED !== $this->status) {
            return;
        }
        $options = $blocking ? 0 : WNOHANG | WUNTRACED;
        $result = pcntl_waitpid($this->getPid(), $this->statusInfo, $options);
        if ($result == -1) {
            throw new RuntimeException("Error waits on or returns the status of the process");
        } elseif ($result === 0) {
            $this->status = self::STATUS_STARTED;
        } else {
            //The process is terminated
            $this->status = self::STATUS_TERMINATED;

            if (pcntl_wifexited($this->statusInfo)) {
                $this->exitCode = pcntl_wexitstatus($this->statusInfo);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(int $signal = null): void
    {
        $this->status = self::STATUS_TERMINATED;
        $this->signal($signal);
    }

    /**
     * Ensures the process is terminated, throws a LogicException if the process has a status different than "terminated".
     *
     * @throws LogicException if the process is not yet terminated
     */
    private function requireProcessIsTerminated(string $functionName): void
    {
        if (!$this->isTerminated()) {
            throw new LogicException(sprintf('Process must be terminated before calling "%s()".', $functionName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getExitCode(): ?int
    {
        $this->updateStatus(false);

        return $this->exitCode;
    }

    /**
     * Returns true if the child process has been terminated by an uncaught signal.
     *
     * It always returns false on Windows.
     *
     * @return bool
     *
     * @throws LogicException In case the process is not terminated
     */
    public function hasBeenSignaled(): bool
    {
        $this->requireProcessIsTerminated(__FUNCTION__);

        return pcntl_wifsignaled($this->statusInfo);
    }

    /**
     * Returns the number of the signal that caused the child process to terminate its execution.
     *
     * It is only meaningful if hasBeenSignaled() returns true.
     *
     * @return int
     *
     * @throws RuntimeException In case --enable-sigchild is activated
     * @throws LogicException   In case the process is not terminated
     */
    public function getTermSignal(): int
    {
        $this->requireProcessIsTerminated(__FUNCTION__);

        return pcntl_wtermsig($this->statusInfo);
    }

    /**
     * Returns true if the child process has been stopped by a signal.
     *
     * It always returns false on Windows.
     *
     * @return bool
     *
     * @throws LogicException In case the process is not terminated
     */
    public function hasBeenStopped(): bool
    {
        $this->requireProcessIsTerminated(__FUNCTION__);

        return pcntl_wifstopped($this->statusInfo);
    }

    /**
     * Returns the number of the signal that caused the child process to stop its execution.
     *
     * It is only meaningful if hasBeenStopped() returns true.
     *
     * @return int
     *
     * @throws LogicException In case the process is not terminated
     */
    public function getStopSignal(): int
    {
        $this->requireProcessIsTerminated(__FUNCTION__);

        return pcntl_wstopsig($this->statusInfo);
    }
}