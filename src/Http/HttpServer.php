<?php

namespace FastServer\Http;

use FastServer\ProtocolParserInterface;
use FastServer\TcpServer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HttpServer extends TcpServer
{
    /**
     * @var RequestHandlerInterface
     */
    protected $requestHandler;

    public function __construct(ProtocolParserInterface $parser)
    {
        $parser = new RequestHeaderParser();
        parent::__construct($parser);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
    }

    protected function createParser()
    {
        $this->parser->on('headers', [$this, 'handle']);
    }

    public function handleRequest(ConnectionInterface $connection, ServerRequestInterface $request)
    {
        $response = $this->requestHandler->handle($request);
    }
}