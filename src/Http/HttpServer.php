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

namespace Viso\Http;

use Evenement\EventEmitter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use React\Socket\ConnectionInterface;
use React\Stream\Util;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Viso\Cluster\Cluster;
use Viso\Http\Exception\InvalidHeaderException;
use Viso\Http\Parser\HttpEmitter;
use Viso\Http\Parser\HttpParser;
use Viso\Server\ConnectionPool;
use Viso\Server\Server;
use Viso\Server\ServerInterface;

final class HttpServer extends EventEmitter implements ServerInterface
{
    /**
     * @var RequestHandlerInterface
     */
    private RequestHandlerInterface $requestHandler;

    private ServerInterface $server;

    private array $options;

    private LoggerInterface $logger;

    private ConnectionPool $connections;

    public function __construct(callable|RequestHandlerInterface $requestHandler, array $options, ?LoggerInterface $logger = null)
    {
        $this->requestHandler = self::normalizeRequestHandler($requestHandler);
        $this->configure($options);
        $this->server = new Server($this->options, [], $logger);
        $this->logger = Cluster::get()->logger();
        $this->connections = $this->server->connections();
        $this->boot();
    }

    /**
     * Configure options resolver for the server.
     *
     * @param OptionsResolver $resolver
     */
    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'keepalive' => true,
                'keepalive_timeout' => 120,
                'keepalive_requests' => 1000,
                'limit_concurrent_requests' => 0
            ])
            ->setIgnoreUndefined()
        ;
    }

    /**
     * Configure the server.
     *
     * @param array $options
     */
    private function configure(array $options): void
    {
        $optionsResolver = new OptionsResolver();
        $this->configureOptions($optionsResolver);
        $this->options = $optionsResolver->resolve($options);
    }

    /**
     * Normalize a request handler.
     *
     * @param callable|RequestHandlerInterface $requestHandler
     * @return RequestHandlerInterface
     */
    private static function normalizeRequestHandler(callable|RequestHandlerInterface $requestHandler): RequestHandlerInterface
    {
        if (is_callable($requestHandler)) {
            $requestHandler = new RequestHandler($requestHandler);
        }
        if (!$requestHandler instanceof RequestHandlerInterface) {
            throw new InvalidHeaderException(sprintf('The request handler must be a valid callback or instance of %s', RequestHandlerInterface::class));
        }
        return $requestHandler;
    }

    /**
     * {@internal}
     * @param ServerRequestInterface $request
     * @param ConnectionInterface $connection
     * @return ResponseInterface
     */
    private function handleRequest(ServerRequestInterface $request, ConnectionInterface $connection): ResponseInterface
    {
        $this->connections->getMetadata($connection)->incrRequest();
        $this->emit('request', [$request, $connection]);
        $response = $this->requestHandler->handle($request);
        $keepalive = $this->options['keepalive'] && 0 !== strcasecmp($request->getHeaderLine('connection'), 'close');
        if ($keepalive) {
            $response = $response->withHeader('Connection', 'Keep-Alive');
        }
        if (!$keepalive) {
            $connection->end();
        }
        return $response;
    }

    private function boot(): void
    {
        Util::forwardEvents($this->server, $this, ['error', 'connection', 'socket']);

        $this->server->on('connection', function(ConnectionInterface $connection){
            $parser = new HttpParser($connection);
            $emitter = new HttpEmitter($connection);
            $parser->on('request', function (ServerRequestInterface $request) use ($connection, $emitter) {
                $response = $this->handleRequest($request, $connection);
                $emitter->emit($response);
            });
        });

        // Add a timer for connections.
        if ($this->options['keepalive']) {
            $this->server->on('worker.start', function (){
                Cluster::get()->loop->addPeriodicTimer(30, [$this, 'closeExpiredConnections']);
            });
        }
    }

    /**
     * @internal
     */
    public function closeExpiredConnections(): void
    {
        $this->logger->debug(sprintf('Checking expired connections(%d).', count($this->connections)));
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

    /**
     * {@inheritdoc}
     */
    public function connections(): ConnectionPool
    {
        return $this->server->connections();
    }

    /**
     * {@inheritdoc}
     */
    public function listen(string $address): void
    {
        $this->server->listen($address);
    }

    /**
     * {@inheritdoc}
     */
    public function close(bool $graceful = true): void
    {
        $this->server->close($graceful);
    }
}