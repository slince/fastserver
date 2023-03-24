<?php

include __DIR__ . '/vendor/autoload.php';

$loop = \React\EventLoop\Loop::get();
$server = new \React\Socket\SocketServer('tcp://127.0.0.1:1234', [], $loop);

const WORKER_NUM = 3;

for ($i = 0; $i <= WORKER_NUM; $i++) {
    $pid = pcntl_fork();
    if (-1 === $pid) {
        exit('fork error');
    } elseif ($pid) {

    } else {
        $loop->run();
    }

}

pcntl_wait($status);