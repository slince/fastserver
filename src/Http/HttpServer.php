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
use React\EventLoop\LoopInterface;
use React\Http\HttpServer as ReactHttpServer;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Viso\Cluster\Cluster;
use Viso\Http\Exception\InvalidHeaderException;
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

    private LoopInterface $loop;
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

    private function createHttpReader(): ReactHttpServer
    {
        return new ReactHttpServer(
            Cluster::get()->loop,
            new StreamingRequestMiddleware(),
            new LimitConcurrentRequestsMiddleware($this->config['limit-concurrent-requests'] ?? 1024),
            new RequestBodyBufferMiddleware($this->config['request-body-buffer'] ?? 65536),
            new RequestBodyParserMiddleware(),
            [$this, 'onRequest']
        );
    }

    public function onRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->server->connections()->getMetadata($connection)->incrRequest();
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

        return $this->requestHandler->handle($request);
    }

    private function boot(): void
    {
        $httpServer = $this->createHttpReader();

        $this->server->on('error', function (\Exception $error) {
            $this->emit('error', [$error]);
        });

        // Add a timer for connections.
        if ($this->options['keepalive']) {
            $this->server->on('worker.start', function (){
                $this->server->getLoop()->addPeriodicTimer(5, [$this, 'closeExpiredConnections']);
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