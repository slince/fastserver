<?php

namespace FastServer\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;
use React\Socket\Connection;
use React\Socket\ServerInterface;
use RuntimeException;

/**
 * The `UnixServer` class implements the `ServerInterface` and
 * is responsible for accepting plaintext connections on unix domain sockets.
 *
 * ```php
 * $server = new React\Socket\UnixServer('unix:///tmp/app.sock', $loop);
 * ```
 *
 * See also the `ServerInterface` for more details.
 *
 * @see ServerInterface
 * @see ConnectionInterface
 */
final class UnixServer extends EventEmitter implements ServerInterface
{
    private $master;
    private $loop;
    private $listening = false;

    /**
     * Creates a plaintext socket server and starts listening on the given unix socket
     *
     * This starts accepting new incoming connections on the given address.
     * See also the `connection event` documented in the `ServerInterface`
     * for more details.
     *
     * ```php
     * $server = new React\Socket\UnixServer('unix:///tmp/app.sock', $loop);
     * ```
     *
     * @param resource $socket
     * @param LoopInterface $loop
     * @throws InvalidArgumentException if the listening address is invalid
     * @throws RuntimeException if listening on this address fails (already in use etc.)
     */
    public function __construct($socket, LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->master = $socket;
        \stream_set_blocking($this->master, 0);

        $this->resume();
    }

    /**
     * Create socket.
     *
     * @param string $path
     * @param array $context
     * @return resource
     */
    public static function createSocket($path, array $context = array())
    {
        if (\strpos($path, '://') === false) {
            $path = 'unix://' . $path;
        } elseif (\substr($path, 0, 7) !== 'unix://') {
            throw new \InvalidArgumentException('Given URI "' . $path . '" is invalid');
        }

        $socket = @\stream_socket_server(
            $path,
            $errno,
            $errstr,
            \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN,
            \stream_context_create(array('socket' => $context))
        );
        if (false === $socket) {
            // PHP does not seem to report errno/errstr for Unix domain sockets (UDS) right now.
            // This only applies to UDS server sockets, see also https://3v4l.org/NAhpr.
            // Parse PHP warning message containing unknown error, HHVM reports proper info at least.
            if ($errno === 0 && $errstr === '') {
                $error = \error_get_last();
                if (\preg_match('/\(([^\)]+)\)|\[(\d+)\]: (.*)/', $error['message'], $match)) {
                    $errstr = isset($match[3]) ? $match['3'] : $match[1];
                    $errno = isset($match[2]) ? (int)$match[2] : 0;
                }
            }

            throw new \RuntimeException('Failed to listen on Unix domain socket "' . $path . '": ' . $errstr, $errno);
        }
        return $socket;
    }

    /**
     * Create a unix server.
     *
     * @param string $path
     * @param LoopInterface $loop
     * @param array $context
     * @return UnixServer
     */
    public static function listen($path, LoopInterface $loop, array $context = [])
    {
        $socket = static::createSocket($path, $context);
        return new static($socket, $loop);
    }

    public function getAddress()
    {
        if (!\is_resource($this->master)) {
            return null;
        }

        return 'unix://' . \stream_socket_get_name($this->master, false);
    }

    public function pause()
    {
        if (!$this->listening) {
            return;
        }

        $this->loop->removeReadStream($this->master);
        $this->listening = false;
    }

    public function resume()
    {
        if ($this->listening || !is_resource($this->master)) {
            return;
        }

        $that = $this;
        $this->loop->addReadStream($this->master, function ($master) use ($that) {
            $newSocket = @\stream_socket_accept($master);
            if (false === $newSocket) {
                $that->emit('error', array(new \RuntimeException('Error accepting new connection')));

                return;
            }
            $that->handleConnection($newSocket);
        });
        $this->listening = true;
    }

    public function close()
    {
        if (!\is_resource($this->master)) {
            return;
        }

        $this->pause();
        \fclose($this->master);
        $this->removeAllListeners();
    }

    /** @internal */
    public function handleConnection($socket)
    {
        $connection = new Connection($socket, $this->loop);
        $connection->unix = true;

        $this->emit('connection', array(
            $connection
        ));
    }
}