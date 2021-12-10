<?php

use FastServer\Http\HttpServer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;

include __DIR__ . '/vendor/autoload.php';

$logger = new Logger("fastserver");
$logger->pushHandler(new StreamHandler(STDOUT, 'info'));

$server = new HttpServer($logger);

$server->configure([
    'address' => '127.0.0.1:2345',
    'max_workers' => 1,
    'reuseport' => true,
    'keepalive' => true,
    'keepalive_timeout' => 3600,
    'keepalive_requests' => 10000
]);

$i = 0;

$server->handle(function(ServerRequestInterface $request) use(&$i){
    $i++;
    return new Response(200, [], "hello {$i}");
});

$server->serve();