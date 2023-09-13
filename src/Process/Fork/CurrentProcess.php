<?php

namespace Wave\Process\Fork;

final class CurrentProcess
{
    /**
     * @var int|null
     */
    protected ?int $pid;

    public function __construct()
    {
        pcntl_async_signals(true);
    }

    public function pid(): int
    {
        if (null === $this->pid) {
            $this->pid = posix_getpid();
        }
        return $this->pid;
    }


    /**
     * Registers a callback for some signals.
     *
     * @param array|int $signals a signal or an array of signals
     * @param callable $callback
     * @param bool $restartSysCalls
     */
    public function signal(array|int $signals, callable $callback, bool $restartSysCalls = true): void
    {
        foreach ((array)$signals as $signal) {
            pcntl_signal($signal, $callback, $restartSysCalls);
        }
    }

    /**
     * Gets the handler for a signal
     * @param int $signal
     * @return callable|int
     */
    public function getSignalHandler(int $signal): callable|int
    {
        return pcntl_signal_get_handler($signal);
    }

    public function wait(callable $callback = null)
    {
        if (null === $callback) {
            $callback = function(int $pid, StatusInfo $status){
                // ignore logic.
            };
        }
        while (true) {
            $pid = \pcntl_wait($status);
            $statusInfo = new StatusInfo($status);
            call_user_func($callback, $pid, $statusInfo);
        }
    }
}