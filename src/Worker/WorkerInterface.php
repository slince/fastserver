<?php

namespace FastServer\Worker;

interface WorkerInterface
{
    public function start();

    public function stop();

    public function work();
}