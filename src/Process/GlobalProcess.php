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

    public function signal(int $signal, callable $callback)
    {
        pcntl_signal($signal, $callback);
    }

    public function wait(callable $callback = null)
    {
        if (null === $callback) {
            $callback = function(int $pid, StatusInfo $status){
                // ignore logic.
            };
        }
        while (true) {
            $pid = \pcntl_waitpid(-1, $status);
            $statusInfo = new StatusInfo($status);
            call_user_func($callback, $pid, $statusInfo);
        }
    }
}