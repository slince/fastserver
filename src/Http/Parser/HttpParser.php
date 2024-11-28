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

namespace Viso\Http\Parser;

use Evenement\EventEmitter;
use GuzzleHttp\Psr7\ServerRequest;
use React\Socket\ConnectionInterface;
use Viso\Http\Exception\InvalidHeaderException;

final class HttpParser extends EventEmitter
{
    public const CRLF = "\r\n";
    public const HEADER_BODY_DELIMITER = "\r\n\r\n";

    /**
     * @var string
     */
    protected string $buffer = '';

    private ConnectionInterface $connection;

    private \Closure $listener;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;

        $this->listener = function(string $chunk){
            $this->buffer .= $chunk;

            while (false !== ($pos = strpos($this->buffer, self::HEADER_BODY_DELIMITER))) {
                $header = substr($this->buffer, 0, $pos);
                $request = $this->parserHeader($header);
                $this->pause();

                if ($request->hasHeader('Content-Length')) {
                    $contentLength = (int)$request->getHeaderLine('Content-Length');
                    $body = new LimitedLengthBody($this->connection, $contentLength);
                } elseif ($request->hasHeader('Transfer-Encoding')) {
                    $body = new ChunkedBody();
                } else {
                    throw new InvalidHeaderException('Unable to recognize the transmission method of the body');
                }
                $body->on('end', fn() => $this->resume());

                $request = $request->withBody(new AsyncStream($body));
                $this->emit('request', [$request]);

                // reset buffer
                $this->buffer = substr($this->buffer, $pos);
            }
        };

        $this->resume();
    }

    private function pause(): void
    {
        $this->connection->removeListener('data', $this->listener);
    }

    private function resume(): void
    {
        $this->connection->on('data', $this->listener);
    }

    protected function parserHeader(string $header): ServerRequest
    {
        if (!\preg_match('#^(?<method>[^ ]+) (?<target>[^ ]+) HTTP/(?<version>\d\.\d)#m', $header, $start)) {
            throw new InvalidHeaderException('Unable to parse invalid request-line');
        }

        // only support HTTP/1.1 and HTTP/1.0 requests
        if ($start['version'] !== '1.1' && $start['version'] !== '1.0') {
            throw new InvalidHeaderException('Received request with invalid protocol version', 505);
        }

        $headers = Rfc7230::parseHeaders(ltrim(strstr($header, self::CRLF), self::CRLF) . self::CRLF);

        // format all header fields into associative array
        $uri = $this->parseUri($headers, $start);

        return new ServerRequest(
            $start['method'],
            $uri,
            $headers,
            null,
            $start['version']
        );
    }

    protected function parseUri(array $headers, array $start)
    {
        $host = null;
        foreach ($headers as $header => $value) {
            // match `Host` request header
            if (strtolower($header) === 'host') {
                $host = $value[0];
            }
        }
        // scheme is `http` unless TLS is used
        $localParts = \parse_url($this->connection->getLocalAddress());
        if (isset($localParts['scheme']) && $localParts['scheme'] === 'tls') {
            $scheme = 'https://';
        } else {
            $scheme = 'http://';
        }

        // default host if unset comes from local socket address or defaults to localhost
        if ($host === null) {
            $host = isset($localParts['host'], $localParts['port']) ? $localParts['host'] . ':' . $localParts['port'] : '127.0.0.1';
        }

        if ($start['method'] === 'OPTIONS' && $start['target'] === '*') {
            // support asterisk-form for `OPTIONS *` request line only
            $uri = $scheme . $host;
        } elseif ($start['method'] === 'CONNECT') {
            $parts = \parse_url('tcp://' . $start['target']);

            // check this is a valid authority-form request-target (host:port)
            if (!isset($parts['scheme'], $parts['host'], $parts['port']) || \count($parts) !== 3) {
                throw new InvalidHeaderException('CONNECT method MUST use authority-form request target');
            }
            $uri = $scheme . $start['target'];
        } else {
            // support absolute-form or origin-form for proxy requests
            if ($start['target'][0] === '/') {
                $uri = $scheme . $host . $start['target'];
            } else {
                // ensure absolute-form request-target contains a valid URI
                $parts = \parse_url($start['target']);

                // make sure value contains valid host component (IP or hostname), but no fragment
                if (!isset($parts['scheme'], $parts['host']) || $parts['scheme'] !== 'http' || isset($parts['fragment'])) {
                    throw new InvalidHeaderException('Invalid absolute-form request-target');
                }

                $uri = $start['target'];
            }
        }

        return $uri;
    }
}