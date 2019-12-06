<?php

namespace FastServer\Process;

class Process implements ProcessInterface
{
    protected $callback;

    public function __construct($callback)
    {
        if (!function_exists('pcntl_fork')) {
        }
        $this->callback = $callback;
    }
}