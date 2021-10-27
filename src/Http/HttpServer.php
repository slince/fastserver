<?php

declare(strict_types=1);

/*
 * This file is part of the fastserver/fastserver package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FastServer\Http;

use FastServer\TcpServer;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\RequestHeaderParser;
use React\Http\StreamingServer;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HttpServer extends TcpServer
{
    /**
     * @var RequestHeaderParser
     */
    protected $parser;

    /**
     * @var StreamingServer
     */
    protected $streamServer;

    /**
     * {@inheritdoc}
     */
    protected function allowedEventNames(): array
    {
        $eventNames = parent::allowedEventNames();
        $eventNames[] = 'request';
        return $eventNames;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
        $this->parser = new RequestHeaderParser();
        $this->streamServer = new StreamingServer(function(ServerRequestInterface $request){
            $this->emit('request', [$request]);
        });
        $this->parser->on('headers', function (ServerRequestInterface $request, ConnectionInterface $conn) {
            $this->streamServer->handleRequest($conn, $request);
        });

        $this->parser->on('error', function(\Exception $e, ConnectionInterface $conn)  {
            $this->emit('error', array($e));
            // parsing failed => assume dummy request and send appropriate error
            $this->streamServer->writeError(
                $conn,
                $e->getCode() !== 0 ? $e->getCode() : 400,
                new ServerRequest('GET', '/')
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function handleConnection(ConnectionInterface $connection)
    {
        $this->parser->handle($connection);
    }
}