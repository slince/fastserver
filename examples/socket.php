<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use React\Socket\ConnectionInterface;
use Viso\Server\Server;

include __DIR__ . '/../vendor/autoload.php';

$logger = new Logger("waveman", [
    new StreamHandler(STDOUT)
]);

$server = new Server(['address' => 'tcp://127.0.0.1:2345', 'worker_num' => 1], $logger);

$server->on('connection', function (ConnectionInterface $connection) {
    echo '[' . $connection->getRemoteAddress() . ' connected]' . PHP_EOL;

    $connection->once('data', function () use ($connection) {
        $body = "Hello world!\r\n";
        $connection->end("HTTP/1.1 200 OK\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
    });

    $connection->on('close', function () use ($connection) {
        echo '[' . $connection->getRemoteAddress() . ' disconnected]' . PHP_EOL;
    });
});

$server->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$server->serve();


