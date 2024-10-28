<?php

use React\Socket\ConnectionInterface;
use Viso\Cluster\Cluster;
use Viso\Cluster\Command\CommandInterface;

include __DIR__ . '/../vendor/autoload.php';

$logger = include __DIR__ . '/logger.php';

$cluster = Cluster::create(function(Cluster $cluster){

//    throw new \Viso\Server\Exception\RuntimeException("bad runtime");

    $cluster->worker->on('command', function(CommandInterface $command) use($cluster){
        $cluster->logger()->info(sprintf('received command: %s', $command->getCommandId()));
    });

    $cluster->worker->on('close', function () use($cluster){
        // close the worker.
        $cluster->logger()->info('close the worker');
    });

    $cluster->worker->onSignals([\SIGTERM], function(int $signal) use($cluster){
        $cluster->logger()->info(sprintf('received signal:%d', $signal));
    });

    $cluster->worker->on('pong', function() use($cluster){
        $cluster->logger()->info('received pong from cluster');
    });

    $socket = $cluster->listen('tcp://127.0.0.1:2345');

    $socket->on('connection', function (ConnectionInterface $connection) use($cluster){
        $cluster->logger()->info('[' . $connection->getRemoteAddress() . ' connected]');

        $connection->once('data', function () use ($connection) {
            $body = "Hello world!\r\n";
            $connection->write("HTTP/1.1 200 OK\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
        });

        $connection->on('close', function () use ($connection, $cluster){
            $cluster->logger()->info('[' . $connection->getRemoteAddress() . ' disconnected]');
        });
    });

    $socket->on('error', function (Exception $e) {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
    });
}, $logger);

if ($cluster->primary) {
    $worker = $cluster->fork();
    $worker->on('message', function (string $message) use($cluster){
        $cluster->logger()->info(sprintf('received message from worker: %s', $message));
    });
    $worker->on('ping', function() use($worker, $cluster){
        $cluster->logger()->info(sprintf('the worker %d is alive', $worker->getId()));
    });
    $worker->on('close', function () use($cluster){
        $cluster->logger()->info('fork new worker');
        $cluster->fork();
    });
}

$cluster->run();