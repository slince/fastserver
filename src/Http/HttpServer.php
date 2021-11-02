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
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

final class HttpServer extends TcpServer
{
    /**
     * @var RequestHandlerInterface
     */
    protected $requestHandler;

    public function __construct(LoggerInterface $logger = null, ?LoopInterface $loop = null)
    {
        parent::__construct(new ParserFactory(HttpParser::class, HttpEmitter::class), $logger, $loop);
    }
//
//    protected function initialize()
//    {
//        $this->on('message', function($message, HttpEmitter $writer){
//            $response = $this->requestHandler->handle($message);
//            $writer->write($response);
//        });
//    }
}