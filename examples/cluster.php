<?php

use React\Socket\ConnectionInterface;
use Waveman\Channel\CommandInterface;
use Waveman\Cluster\Cluster;

include __DIR__ . '/../vendor/autoload.php';

$cluster = Cluster::create(function(Cluster $cluster){

    $cluster->worker->on('command', function(CommandInterface $command){
        echo 'received command:', $command->getCommandId(), PHP_EOL;
    });

    $socket = $cluster->listen('tcp://127.0.0.1:2345');

    $socket->on('connection', function (ConnectionInterface $connection) {
        echo '[' . $connection->getRemoteAddress() . ' connected]' . PHP_EOL;

        $connection->once('data', function () use ($connection) {
            $body = "<html><h1>Hello world!</h1></html>\r\n";
            $connection->end("HTTP/1.1 200 OK\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
        });

        $connection->on('close', function () use ($connection) {
            echo '[' . $connection->getRemoteAddress() . ' disconnected]' . PHP_EOL;
        });
    });

    $socket->on('error', function (Exception $e) {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
    });
});

if ($cluster->isPrimary) {
    $worker = $cluster->fork();
    $worker->on('message', function (string $message){
        echo 'received message from worker: ', $message, PHP_EOL;
    });
}

$cluster->run();