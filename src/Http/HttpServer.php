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

use FastServer\Parser\ParserFactory;
use FastServer\TcpServer;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HttpServer extends TcpServer
{
    /**
     * @var ConnectionPool
     */
    protected $connections;

    public function __construct(LoggerInterface $logger = null, ?LoopInterface $loop = null)
    {
        parent::__construct(new ParserFactory(HttpParser::class, HttpEmitter::class), $logger, $loop);
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'keepalive' => true,
            'keepalive_timeout' => 120,
            'keepalive_requests' => 1000
        ]);
    }

    protected function initialize()
    {
        $this->connections = new ConnectionPool();
        $this->on('connection', function(ConnectionInterface $connection){
            $this->connections->add($connection);
        });
        $this->on('close', function(ConnectionInterface $connection){
            $this->connections->remove($connection);
        });
        $this->on('message', function($message, $writer, ConnectionInterface $connection){
            $this->connections->getMetadata($connection)->incrRequest();
        });
        // Add a timer for connections.
        if ($this->options['keepalive']) {
            $this->loop->addPeriodicTimer(5, [$this, 'closeExpiredConnections']);
        }
    }

    /**
     * @internal
     */
    public function closeExpiredConnections()
    {
        $this->logger->info('Check expired connection');
        /* @var ConnectionInterface $connection */
        foreach ($this->connections as $connection) {
            $metadata = $this->connections->getMetadata($connection);
            $this->logger->info('Check expired connection' . $metadata->getConnection()->getLocalAddress());
            if (
                $metadata->getRequests() > $this->options['keepalive_requests']
                || time() - $metadata->getCreatedAt()->getTimestamp() >= $this->options['keepalive_timeout']
            ) {
                $metadata->getConnection()->end();
            }
        }
    }
}