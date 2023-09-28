<?php

declare(strict_types=1);

/*
 * This file is part of the waveman/waveman package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Waveman\Http;

use GuzzleHttp\Psr7\Response;
use Waveman\Http\Exception\InvalidHeaderException;
use Waveman\Http\Parser\HttpEmitter;
use Waveman\Http\Parser\HttpParser;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Socket\ConnectionInterface;
use React\Http\HttpServer as Http;
use React\Http\Middleware;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Waveman\Server\Parser\StreamingReader;
use Waveman\Server\ServerInterface;

final class HttpServer implements ServerInterface
{
    /**
     * @var StreamingReader
     */
    protected StreamingReader $streamReader;

    /**
     * @var RequestHandlerInterface
     */
    protected RequestHandlerInterface $requestHandler;

    public function __construct()
    {
        $http = new Http(
            new Middleware\StreamingRequestMiddleware(),
            new Middleware\LimitConcurrentRequestsMiddleware(100), // 100 concurrent buffering handlers
            new Middleware\RequestBodyBufferMiddleware(2 * 1024 * 1024), // 2 MiB per request
            new Middleware\RequestBodyParserMiddleware(),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
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
    public function handle(callable|RequestHandlerInterface $requestHandler): void
    {
        if (is_callable($requestHandler)) {
            $requestHandler = new RequestHandler($requestHandler);
        }
        if (!$requestHandler instanceof RequestHandlerInterface) {
            throw new InvalidHeaderException(sprintf('The request handler must be a valid callback or instance of %s', RequestHandlerInterface::class));
        }
        $this->requestHandler = $requestHandler;
    }

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
            $this->connections->add($connection);
            $connection->on('close', function() use($connection){
                $this->connections->remove($connection);
            });
            $this->streamReader->listen($connection);
        });

        // Add a timer for connections.
        if ($this->options['keepalive']) {
//            $this->loop->addPeriodicTimer(5, [$this, 'closeExpiredConnections']);
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

    public function configure(array $options): void
    {
        // TODO: Implement configure() method.
    }

    public function on(string $event, callable $listener): void
    {
        // TODO: Implement on() method.
    }

    public function getOption(string $name): mixed
    {
        // TODO: Implement getOption() method.
    }

    public function serve(): void
    {
        // TODO: Implement serve() method.
    }

    public function stop(bool $graceful = true): void
    {
        // TODO: Implement stop() method.
    }
}