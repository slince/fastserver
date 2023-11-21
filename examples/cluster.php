<?php

use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use Viso\Channel\CommandInterface;
use Viso\Cluster\Cluster;

include __DIR__ . '/../vendor/autoload.php';

$cluster = Cluster::create(function(Cluster $cluster){

    $cluster->worker->on('command', function(CommandInterface $command){
        echo 'received command:', $command->getCommandId(), PHP_EOL;
    });

    $cluster->worker->on('close', function (){
        // close the worker.
        echo 'close the worker';
    });

    $cluster->worker->onSignals([\SIGTERM], function(int $signal){
        echo 'received signal:', $signal;
    });

    $socket = $cluster->listen('tcp://127.0.0.1:2345');

    $socket->on('connection', function (ConnectionInterface $connection) {
        echo '[' . $connection->getRemoteAddress() . ' connected]' . PHP_EOL;

        $connection->once('data', function () use ($connection) {
            $body = "Hello world!\r\n";
            $connection->write("HTTP/1.1 200 OK\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
        });

        $connection->on('close', function () use ($connection) {
            echo '[' . $connection->getRemoteAddress() . ' disconnected]' . PHP_EOL;
        });
    });

    $socket->on('error', function (Exception $e) {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
    });

    Loop::get()->run();
});

if ($cluster->primary) {
    $worker = $cluster->fork();
    $worker->on('message', function (string $message){
        echo 'received message from worker: ', $message, PHP_EOL;
    });
    $cluster->on('worker.close', function () use($cluster){
        $cluster->fork();
    });
}

$cluster->run();