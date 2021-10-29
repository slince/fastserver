<?php

use FastServer\Http\HttpServer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ServerRequestInterface;
use React\Socket\ConnectionInterface;

include __DIR__ . '/vendor/autoload.php';

$logger = new Logger("fastserver");
$logger->pushHandler(new StreamHandler(STDOUT));

$server = new HttpServer($logger);

$server->configure([
    'address' => '127.0.0.1:1234',
    'max_workers' => 4
]);

$server->on('connection', function(ConnectionInterface $connection) use($logger){
    $logger->info(sprintf('Accept connection from %s', $connection->getLocalAddress()));
});

$server->on('message', function(ServerRequestInterface $request){
    var_dump((string)$request->getBody());
    var_dump($request->getHeaders());
    return new \React\Http\Response(200, [], 'hello');
});

$server->serve();