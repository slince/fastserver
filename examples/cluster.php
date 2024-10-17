<?php

use React\Socket\ConnectionInterface;
use Viso\Cluster\Cluster;
use Viso\Cluster\ClusterAggregator;
use Viso\Cluster\Command\CommandInterface;

include __DIR__ . '/../vendor/autoload.php';

$cluster = Cluster::create(function(Cluster $cluster){

    $cluster->worker->on('command', function(CommandInterface $command){
        echo 'received command:', $command->getCommandId(), PHP_EOL;
    });

    $cluster->worker->on('close', function (){
        // close the worker.
        echo 'close the worker', PHP_EOL;
    });

    $cluster->worker->onSignals([\SIGTERM], function(int $signal){
        echo 'received signal:', $signal, PHP_EOL;
    });

    $cluster->worker->on('pong', function(){
        echo 'received pong from cluster', PHP_EOL;
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
});

if ($cluster->primary) {
    $worker = $cluster->fork();
    $worker->on('message', function (string $message){
        echo 'received message from worker: ', $message, PHP_EOL;
    });
    $worker->on('ping', function() use($worker){
        echo sprintf('the worker %d is alive', $worker->getId()), PHP_EOL;
    });
    $worker->on('close', function () use($cluster){
        echo 'fork new worker', PHP_EOL;
        $cluster->fork();
    });
}

ClusterAggregator::aggregate($cluster)->run();