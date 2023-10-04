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
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Waveman\Http\Exception\InvalidHeaderException;
use Waveman\Http\Parser\HttpEmitter;
use Waveman\Http\Parser\HttpParser;
use Waveman\Server\Exception\InvalidArgumentException;
use Waveman\Server\Parser\ParserFactory;
use Waveman\Server\Parser\StreamingReader;
use Waveman\Server\Server;
use Waveman\Server\ServerInterface;

final class HttpServer extends EventEmitter implements ServerInterface
{
    private const EVENT_NAMES = ['connection', 'request'];

    /**
     * @var StreamingReader
     */
    protected StreamingReader $streamReader;

    /**
     * @var RequestHandlerInterface
     */
    protected RequestHandlerInterface $requestHandler;

    protected LoggerInterface $logger;

    protected array $options;

    protected ServerInterface $server;

    public function __construct(array $options, ?LoggerInterface $logger = null, ?LoopInterface $loop = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->server = new Server($options, $this->logger, $loop);
        $this->configure($options);
    }

    /**
     * Configure options resolver for the server.
     *
     * @param OptionsResolver $resolver
     */
    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'keepalive' => true,
            'keepalive_timeout' => 120,
            'keepalive_requests' => 1000
        ]);
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

    protected function boot()
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

        $this->server->on('connection', function(ConnectionInterface $connection){
            $this->connections->add($connection);
            $connection->on('close', function() use($connection){
                $this->connections->remove($connection);
            });
            $this->streamReader->listen($connection);
        });

        // Add a timer for connections.
        if ($this->options['keepalive']) {
            $this->server->getLoop()->addPeriodicTimer(5, [$this, 'closeExpiredConnections']);
        }
    }

    protected static function createStreamReader(): StreamingReader
    {
        $parserFactory = new ParserFactory(HttpParser::class, HttpEmitter::class);
        return new StreamingReader($parserFactory);
    }

    /**
     * @internal
     */
    public function closeExpiredConnections(): void
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

    /**
     * {@inheritdoc}
     */
    public function on(string $event, callable $listener): void
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
    public function stop(bool $graceful = true): void
    {
        $this->server->stop($graceful);
    }
}