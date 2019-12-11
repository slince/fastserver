<?php

namespace FastServer\Http;

use FastServer\ProtocolParserInterface;
use FastServer\TcpServer;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\StreamingServer;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HttpServer extends TcpServer
{
    /**
     * @var callable
     */
    protected $requestHandler;

    /**
     * @var ProtocolParserInterface
     */
    protected $parser;

    /**
     * @var StreamingServer
     */
    protected $streamServer;

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
    }

    public function onRequest(callable $requestHandler)
    {
        $this->requestHandler = $requestHandler;
    }

    protected function initialize()
    {
        $this->parser = new RequestHeaderParser();
        $this->streamServer = new StreamingServer($this->requestHandler);
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
        $this->parser->on('headers', [$this->streamServer, 'handle']);
    }

    /**
     * {@inheritdoc}
     */
    public function handleConnection(ConnectionInterface $connection)
    {
        parent::handleConnection($connection);
        $this->parser->handle($connection);
    }
}