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

use FastServer\Http\Exception\InvalidHeaderException;
use FastServer\Parser\ParserFactory;
use FastServer\Parser\StreamingReader;
use FastServer\TcpServer;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HttpServer extends TcpServer
{
    /**
     * @var ConnectionPool
     */
    protected $connections;

    /**
     * @var StreamingReader
     */
    protected $streamReader;

    /**
     * @var RequestHandlerInterface
     */
    protected $requestHandler;

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'keepalive' => true,
            'keepalive_timeout' => 120,
            'keepalive_requests' => 1000
        ]);
    }

    /**
     * Sets a request handler for the http server.
     *
     * @param callable|RequestHandlerInterface $requestHandler
     */
    public function handle($requestHandler)
    {
        if (is_callable($requestHandler)) {
            $requestHandler = new CallableRequestHandler($requestHandler);
        }
        if (!$requestHandler instanceof RequestHandlerInterface) {
            throw new InvalidHeaderException(sprintf('The request handler must be a valid callback or instance of %s', RequestHandlerInterface::class));
        }
        $this->requestHandler = $requestHandler;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
        $this->connections = new ConnectionPool();

        $this->streamReader = $this->createStreamReader();
        $this->streamReader->on('message', function(ServerRequestInterface $request, HttpEmitter $writer, ConnectionInterface $connection){
            $this->connections->getMetadata($connection)->incrRequest();
            $this->emit('message', [$request, $connection]);
            $response = $this->requestHandler->handle($request);
            $keepalive = $this->options['keepalive'] && 0 !== strcasecmp($request->getHeaderLine('connection'), 'close');
            if ($keepalive) {
                $response = $response->withHeader('Connection', 'Keep-Alive');
            }
            $writer->write($response);
            if (!$keepalive) {
                $connection->end();
            }
        });
        $this->streamReader->on('error', function(\Exception $exception, $writer, ConnectionInterface $connection){
            $response = new Response($exception->getCode() ?: 400, [], $exception->getMessage());
            $writer->write($response);
            $connection->end();
        });

        $this->on('connection', function(ConnectionInterface $connection){
            var_dump('add connection');
            $this->connections->add($connection);
            $connection->on('close', function() use($connection){
                $this->connections->remove($connection);
                var_dump('close connection');
            });
            $this->streamReader->listen($connection);
        });

        // Add a timer for connections.
        if ($this->options['keepalive']) {
            $this->loop->addPeriodicTimer(5, [$this, 'closeExpiredConnections']);
        }
    }

    protected function createStreamReader(): StreamingReader
    {
        $parserFactory = new ParserFactory(HttpParser::class, HttpEmitter::class);
        return new StreamingReader($parserFactory);
    }

    /**
     * @internal
     */
    public function closeExpiredConnections()
    {
        $this->logger->info(sprintf('Checking expired connections(%d).', count($this->connections)));
        /* @var ConnectionInterface $connection */
        foreach ($this->connections as $connection) {
            $metadata = $this->connections->getMetadata($connection);
            if (
                $metadata->getRequests() > $this->options['keepalive_requests']
                || $metadata->getAliveSeconds() >= $this->options['keepalive_timeout']
            ) {
                $this->logger->info(sprintf('The connection %s is expired, close it.', $connection->getRemoteAddress()));
                $connection->end();
            }
        }
    }
}