<?php

include __DIR__ . '/vendor/autoload.php';

$server = new \FastServer\Http\HttpServer();

$server->configure([
    'address' => '127.0.0.1:1234'
]);

$server->onRequest(function(\Psr\Http\Message\ServerRequestInterface $request){
    var_dump((string)$request->getBody());
    var_dump($request->getHeaders());
    return new \React\Http\Response(200, [], 'hello');
});

$server->serve();