<?php

use FastServer\Http\HttpServer;
use FastServer\Http\HttpEmitter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ServerRequestInterface;
use React\Socket\ConnectionInterface;
use GuzzleHttp\Psr7\Response;

include __DIR__ . '/vendor/autoload.php';

$logger = new Logger("fastserver");
$logger->pushHandler(new StreamHandler(STDOUT));

$server = new HttpServer($logger);

$server->configure([
    'address' => '127.0.0.1:1234',
    'max_workers' => 4,
    'keepalive_timeout' => 10,
    'keepalive_requests' => 100
]);


$server->on('connection', function(ConnectionInterface $connection) use($logger){
    $logger->info(sprintf('Accept connection from %s', $connection->getLocalAddress()));
});

$i = 0;

$server->handle(function(ServerRequestInterface $request) use(&$i){
//    print_r($request->getHeaders());
    $i++;
    return new Response(200, [], "hello {$i}");
});


$server->serve();