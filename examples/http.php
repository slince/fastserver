<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Viso\Http\HttpServer;

include __DIR__ . '/../vendor/autoload.php';

$i = 0;

$logger = new Logger("viso", [new StreamHandler(STDOUT)]);
$server = new HttpServer([
    'address' => '127.0.0.1:2345',
    'worker_num' => 1,
    'http' => [
        'keepalive' => true,
        'keepalive_timeout' => 3600,
        'keepalive_requests' => 10000
    ]
], $logger);

$server->serve();