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

use Evenement\EventEmitter;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Waveman\Http\Exception\InvalidHeaderException;
use Waveman\Http\Parser\HttpEmitter;
use Waveman\Http\Parser\HttpParser;
use Waveman\Parser\ParserFactory;
use Waveman\Parser\StreamingReader;
use Waveman\Server\ConnectionPool;
use Waveman\Server\Exception\InvalidArgumentException;
use Waveman\Server\Server;
use Waveman\Server\ServerInterface;

final class HttpServer extends EventEmitter implements ServerInterface
{
    private const EVENT_NAMES = ['connection', 'request', 'error'];

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

    public function __construct(array $options, ?LoggerInterface $logger = null)
    {
        $this->server = new Server($options, $logger);
        $this->connections = $this->server->getConnections();
        $this->logger = $this->server->getLogger();
        $this->configure($options);
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
                'keepalive_requests' => 1000
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

    private function boot(): void
    {
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

        $this->server->on('connection', function(ConnectionInterface $connection){
            $this->emit('connection', [$connection]);
            $this->streamReader->listen($connection);
        });

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

    /**
     * {@inheritdoc}
     */
    public function on($event, callable $listener): void
    {
        if (!in_array($event, self::EVENT_NAMES)) {
            throw new InvalidArgumentException(sprintf('The event "%s" is not supported.', $event));
        }
        parent::on($event, $listener);
    }

    /**
     * {@inheritdoc}
     */
    public function serve(): void
    {
        $this->server->serve();
    }

    /**
     * {@inheritdoc}
     */
    public function close(bool $graceful = true): void
    {
        $this->server->close($graceful);
    }
}