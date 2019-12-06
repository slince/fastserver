<?php

namespace FastServer\Worker;

interface WorkerInterface
{
    public function start();

    public function stop();

    public function getPid();

    public function send($payload);
}