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

$server->on('connection', function(ConnectionInterface $connection) use(&$i){
    $connection->on('data', function(string $data) use($connection, &$i){
        $content = "hello {$i}";
        $length = strlen($content);
        $message = <<<EOT
HTTP/1.1 200 OK
Server: FastServer/1
Date: Thu, 11 Nov 2021 11:02:13 GMT
Content-Length: {$length}

{$content}
EOT;

        $connection->end($message);
        $i++;
    });
});

$server->serve();