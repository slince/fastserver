<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Viso\Http\HttpServer;

include __DIR__ . '/../vendor/autoload.php';

$i = 0;

$logger = new Logger("viso", [new StreamHandler(STDOUT)]);
$server = new HttpServer(function(ServerRequestInterface $request){
    return Response::plaintext("hello");
}, [
    'worker_num' => 1,
    'keepalive' => true,
    'keepalive_timeout' => 3600,
    'keepalive_requests' => 10000
], $logger);

$server->listen('127.0.0.1:2345');