<?php

use FastServer\TcpServer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ServerRequestInterface;
use React\Socket\ConnectionInterface;
use GuzzleHttp\Psr7\Response;

include __DIR__ . '/vendor/autoload.php';

$logger = new Logger("tcp");
$logger->pushHandler(new StreamHandler(STDOUT));

$server = new FastServer\TcpServer($logger);

$server->configure([
    'address' => '127.0.0.1:4567',
    'max_workers' => 4,
]);

$i = 0;

$server->on('connection', function(ConnectionInterface $connection){
    $connection->on('data', function(string $data) use($connection){
        $connection->write($data);
    });
});
$server->handle(function(ServerRequestInterface $request) use(&$i){
    $i++;
    return new Response(200, [], "hello {$i}");
});


$server->serve();