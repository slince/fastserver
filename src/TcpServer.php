<?php

namespace FastServer;

use Amp\Loop;

final class TcpServer
{
    /**
     * @var resource
     */
    protected $socket;

    public function __construct($uri, array $context = [])
    {
        $this->socket = $this->createSocket($uri, $context);
    }

    /**
     * @param string $uri
     * @param array $context
     * @return resource
     */
    protected function createSocket($uri, array $context = [])
    {
        // a single port has been given => assume localhost
        if ((string)(int)$uri === (string)$uri) {
            $uri = '127.0.0.1:' . $uri;
        }
        // assume default scheme if none has been given
        if (\strpos($uri, '://') === false) {
            $uri = 'tcp://' . $uri;
        }
        // parse_url() does not accept null ports (random port assignment) => manually remove
        if (\substr($uri, -2) === ':0') {
            $parts = \parse_url(\substr($uri, 0, -2));
            if ($parts) {
                $parts['port'] = 0;
            }
        } else {
            $parts = \parse_url($uri);
        }
        // ensure URI contains TCP scheme, host and port
        if (!$parts || !isset($parts['scheme'], $parts['host'], $parts['port']) || $parts['scheme'] !== 'tcp') {
            throw new \InvalidArgumentException('Invalid URI "' . $uri . '" given');
        }
        if (false === \filter_var(\trim($parts['host'], '[]'), \FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('Given URI "' . $uri . '" does not contain a valid host IP');
        }
        $socket = @\stream_socket_server(
            $uri,
            $errno,
            $errstr,
            \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN,
            \stream_context_create(['socket' => $context])
        );
        if (false === $socket) {
            throw new \RuntimeException('Failed to listen on "' . $uri . '": ' . $errstr, $errno);
        }
        return $socket;
    }

    public function listen()
    {
        stream_set_blocking($this->socket, false);
        Loop::onReadable($this->socket, function($watcherId, $socket){
            $newSocket = @\stream_socket_accept($socket);
            if (false === $newSocket) {
                $this->emit('error', array(new \RuntimeException('Error accepting new connection')));
                return;
            }
            $this->handleConnection($newSocket);
        });
    }
}