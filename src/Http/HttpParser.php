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

use FastServer\Http\Exception\InvalidHeaderException;
use FastServer\Parser\ParserInterface;
use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\ServerRequest;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;

class HttpParser implements ParserInterface
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * @var int
     */
    protected $length;

    /**
     * @var ServerRequest
     */
    protected $request;

    /**
     * @var int|null
     */
    protected $contentLength;

    public function push(string $chunk)
    {
        $this->buffer .= $chunk;
        $this->length += strlen($chunk);
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(): array
    {
        $requests = [];

        // parse http request header
        if (null === $this->request && false !== ($pos = strpos($this->buffer, "\r\n\r\n"))) {
            $header = substr($this->buffer, 0, $pos);
            $this->request = $this->parserHeader($header);
            $this->contentLength = 0;
            if ($this->request->hasHeader('Content-Length')) {
                $this->contentLength = (int)$this->request->getHeaderLine('Content-Length');
            } elseif ($this->request->hasHeader('Transfer-Encoding')) {
                $this->contentLength = null;
            }
           // reset buffer
            $this->buffer = substr($this->buffer, $pos);
            $this->length -= $pos;
        }

        if (null !== $this->request) {
            if (null === $this->contentLength) {

            } else if ($this->contentLength > 0) {
                if ($this->length >= ($length = $this->contentLength + 4)) {
                    $content = substr($this->buffer, 0, $length);
                    $this->buffer = substr($this->buffer, $length);
                    $this->length -= $length;

                    $body = new BufferStream();
                    $body->write($content);
                }
            } else {
                $body = new BufferStream();
            }
        }
    }

    protected function parserHeader(string $header)
    {
        if (!\preg_match('#^(?<method>[^ ]+) (?<target>[^ ]+) HTTP/(?<version>\d\.\d)#m', $header, $start)) {
            throw new InvalidHeaderException('Unable to parse invalid request-line');
        }

        // only support HTTP/1.1 and HTTP/1.0 requests
        if ($start['version'] !== '1.1' && $start['version'] !== '1.0') {
            throw new InvalidHeaderException('Received request with invalid protocol version', 505);
        }

        $headers = Rfc7230::parseHeaders($header);

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
                $host = $value;
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