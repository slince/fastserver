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
$logger->pushHandler(new StreamHandler(STDOUT, 'info'));

$server = new HttpServer($logger);

$server->configure([
    'address' => '127.0.0.1:1234',
    'max_workers' => 4,
    'keepalive' => true,
    'keepalive_timeout' => 3600,
    'keepalive_requests' => 10000
]);

$i = 0;

$server->handle(function(ServerRequestInterface $request) use(&$i){
//    print_r($request->getHeaders());
    $i++;
    return new Response(200, [], "hello {$i}");
});


//$server->on('message', function($message, ConnectionInterface $connection) use($logger){
//    $logger->info($connection->getLocalAddress() . '*' . $connection->getRemoteAddress());
//});
$server->serve();