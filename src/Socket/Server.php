<?php

namespace FastServer\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use Exception;
use React\Socket\ConnectionInterface;
use React\Socket\SecureServer;
use React\Socket\ServerInterface;

final class Server extends EventEmitter implements ServerInterface
{
    private $decoratedServer;

    public function __construct($uri, LoopInterface $loop, array $context = array())
    {
        list($scheme, $uri, $context) = static::resolveOptions($uri, $context);

        if ($scheme === 'unix') {
            $server = UnixServer::listen($uri, $loop, $context['unix']);
        } else {
            $server = TcpServer::listen(str_replace('tls://', '', $uri), $loop, $context['tcp']);

            if ($scheme === 'tls') {
                $server = new SecureServer($server, $loop, $context['tls']);
            }
        }

        $this->decoratedServer = $server;

        $that = $this;
        $server->on('connection', function (ConnectionInterface $conn) use ($that) {
            $that->emit('connection', array($conn));
        });
        $server->on('error', function (Exception $error) use ($that) {
            $that->emit('error', array($error));
        });
    }

    public static function listen($uri, LoopInterface $loop, array $context = array())
    {
        return new static($uri, $loop, $context);
    }

    public static function createSocket($uri, array $context = [])
    {
        // sanitize TCP context options if not properly wrapped
        list($scheme, $uri, $context) = static::resolveOptions($uri, $context);

        return $scheme === 'unix' ? UnixServer::createSocket($uri, $context['unix'])
            : TcpServer::createSocket(str_replace('tls://', '', $uri), $context['tcp']);
    }

    protected static function resolveOptions($uri, array $context = [])
    {
        if ($context && (!isset($context['tcp']) && !isset($context['tls']) && !isset($context['unix']))) {
            $context = array('tcp' => $context);
        }

        // apply default options if not explicitly given
        $context += array(
            'tcp' => array(),
            'tls' => array(),
            'unix' => array()
        );

        $scheme = 'tcp';
        $pos = \strpos($uri, '://');
        if ($pos !== false) {
            $scheme = \substr($uri, 0, $pos);
        }
        return [$scheme, $uri, $context];
    }

    public function getAddress()
    {
        return $this->decoratedServer->getAddress();
    }

    public function pause()
    {
        $this->decoratedServer->pause();
    }

    public function resume()
    {
        $this->decoratedServer->resume();
    }

    public function close()
    {
        $this->decoratedServer->close();
    }
}