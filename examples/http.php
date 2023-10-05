<?php

use GuzzleHttp\Psr7\Response;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Waveman\Http\HttpServer;

include __DIR__ . '/../vendor/autoload.php';

$logger = new Logger("waveman", [
    new StreamHandler(STDOUT)
]);

$server = new HttpServer([
    'address' => '127.0.0.1:2345',
    'max_workers' => 1,
    'keepalive' => true,
    'keepalive_timeout' => 3600,
    'keepalive_requests' => 10000
], $logger);

$i = 0;

$server->handle(function(ServerRequestInterface $request) use(&$i){
    $i++;
    return new Response(200, [], "hello {$i}");
});

$server->serve();