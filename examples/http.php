<?php

use FastServer\Http\HttpServer;

include __DIR__ . '/../vendor/autoload.php';


$server = new HttpServer();

$server->configure([
    'address' => '127.0.0.1:2345',
    'max_workers' => 1,
    'reuseport' => true,
    'keepalive' => true,
    'keepalive_timeout' => 3600,
    'keepalive_requests' => 10000
]);

$i = 0;

$server->handle(function(Strem $request) use(&$i){
    $i++;
    return new Response(200, [], "hello {$i}");
});

$server->serve();