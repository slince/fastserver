<?php

namespace FastServer\Process;

use FastServer\Exception\LogicException;
use FastServer\Exception\RuntimeException;

final class StatusInfo
{
    /**
     * @var int
     */
    protected int $status;

    public function __construct(int $status)
    {
        $this->status = $status;
    }

    /**
     * Returns true if the child process has been exited normally
     *
     * It always returns false on Windows.
     *
     * @return bool
     *
     * @throws LogicException In case the process is not terminated
     */
    public function hasBeenExited(): bool
    {
        return pcntl_wifexited($this->status);
    }

    /**
     * Returns the status code of the child process
     *
     * It is only meaningful if hasBeenExited() returns true.
     *
     * @return int
     *
     * @throws RuntimeException In case --enable-sigchild is activated
     * @throws LogicException   In case the process is not terminated
     */
    public function getStatusCode(): int
    {
        return pcntl_wexitstatus($this->status);
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
        return pcntl_wifsignaled($this->status);
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
        return pcntl_wtermsig($this->status);
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
        return pcntl_wifstopped($this->status);
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
        return pcntl_wstopsig($this->status);
    }
}