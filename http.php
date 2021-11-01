<?php

use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

$http_worker = new Worker('http://0.0.0.0:2345');

// 4 processes
$http_worker->count = 4;

// Emitted when data received
$http_worker->onMessage = function ($connection, $request) {

    // Send data to client
    $connection->send("Hello World" . get_class($connection));
};

// Run all workers
Worker::runAll();