<?php

namespace FastServer\Process;

final class GlobalProcess
{
    /**
     * @var int
     */
    protected $pid;

    /**
     * @var static
     */
    protected static $process;

    public function __construct()
    {
        pcntl_async_signals(true);
    }

    public static function get(): GlobalProcess
    {
        if (null === self::$process) {
            self::$process = new GlobalProcess();
        }
        return self::$process;
    }

    public function pid(): int
    {
        if (null === $this->pid) {
            $this->pid = posix_getpid();
        }
        return $this->pid;
    }

    public function signal($signals, callable $callback, bool $restartSyscalls = true)
    {
        $signals = (array)$signals;
        foreach ($signals as $signal) {
            pcntl_signal($signal, $callback, $restartSyscalls);
        }
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