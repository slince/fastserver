<?php

use GuzzleHttp\Psr7\Response;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Viso\Http\HttpPlugin;
use Viso\Server\Server;

include __DIR__ . '/../vendor/autoload.php';

$i = 0;
$http = new HttpPlugin(function(ServerRequestInterface $request) use(&$i){
    $i++;
    return new Response(200, [], "hello {$i}");
});

$logger = new Logger("viso", [new StreamHandler(STDOUT)]);
$server = new Server([$http], [
    'address' => '127.0.0.1:2345',
    'worker_num' => 1,
    'http' => [
        'keepalive' => true,
        'keepalive_timeout' => 3600,
        'keepalive_requests' => 10000
    ]
], $logger);

$server->serve();