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

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Viso\Cluster\Cluster;
use Viso\Cluster\ConnectionPool;
use Viso\Http\Exception\InvalidHeaderException;
use Viso\Http\Parser\HttpEmitter;
use Viso\Http\Parser\HttpParser;
use Viso\Parser\ParserFactory;
use Viso\Parser\StreamingReader;
use Viso\Server\PluginInterface;
use Viso\Server\ServerInterface;

final class HttpPlugin implements PluginInterface
{
    private const EVENT_NAMES = ['request'];

    /**
     * @var StreamingReader
     */
    private StreamingReader $streamReader;

    /**
     * @var RequestHandlerInterface
     */
    private RequestHandlerInterface $requestHandler;

    private ServerInterface $server;

    private LoggerInterface $logger;

    private ConnectionPool $connections;

    private array $options;

    /**
     * Sets a request handler for the http server.
     *
     * @param callable|RequestHandlerInterface $requestHandler
     */
    public function __construct(callable|RequestHandlerInterface $requestHandler)
    {
        if (is_callable($requestHandler)) {
            $requestHandler = new RequestHandler($requestHandler);
        }
        if (!$requestHandler instanceof RequestHandlerInterface) {
            throw new InvalidHeaderException(sprintf('The request handler must be a valid callback or instance of %s', RequestHandlerInterface::class));
        }
        $this->requestHandler = $requestHandler;
        $this->streamReader = $this->createStreamReader();
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'http';
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents(): array
    {
        return self::EVENT_NAMES;
    }

    /**
     * {@inheritdoc}
     */
    public function activate(ServerInterface $server, array $options): void
    {
        $this->server = $server;
        $this->options = $options;
        $this->connections = $this->server->getConnections();
        $this->logger = $this->server->getLogger();
        $this->boot();
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'keepalive' => true,
                'keepalive_timeout' => 120,
                'keepalive_requests' => 1000
            ])
        ;
    }

    private function boot(): void
    {
        $this->streamReader->on('message', function(ServerRequestInterface $request, HttpEmitter $writer, ConnectionInterface $connection){
            $this->connections->getMetadata($connection)->incrRequest();
            $this->server->emit('request', [$request, $connection]);
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

        $this->server->on('connection', function(ConnectionInterface $connection){
            $this->streamReader->listen($connection);
        });

        // Add a timer for connections.
        if ($this->options['keepalive']) {
            $this->server->on('worker.start', function (){
                if (!Cluster::get()->isPrimary) {
                    Loop::get()->addPeriodicTimer(5, [$this, 'closeExpiredConnections']);
                }
            });
        }
    }

    private static function createStreamReader(): StreamingReader
    {
        $parserFactory = new ParserFactory(HttpParser::class, HttpEmitter::class);
        return new StreamingReader($parserFactory);
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
}