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

use FastServer\TcpServer;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\RequestHeaderParser;
use React\Http\StreamingServer;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HttpServer extends TcpServer
{
    /**
     * @var RequestHeaderParser
     */
    protected $parser;

    /**
     * @var StreamingServer
     */
    protected $streamServer;

    /**
     * {@inheritdoc}
     */
    protected function allowedEventNames(): array
    {
        return ['request'];
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
        $this->parser = new RequestHeaderParser();
        $this->streamServer = new StreamingServer(function(ServerRequestInterface $request){
            $this->emit('request', [$request]);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function handleConnection(ConnectionInterface $connection)
    {
        $this->parser->handle($connection);
    }
}