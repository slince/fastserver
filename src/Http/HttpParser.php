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

use FastServer\StreamAwareInterface;
use FastServer\Http\Exception\InvalidHeaderException;
use FastServer\Parser\ParserInterface;
use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\ServerRequest;
use React\Socket\ConnectionInterface;
use React\Stream\DuplexStreamInterface;

class HttpParser implements ParserInterface, StreamAwareInterface
{
    public const CRLF = "\r\n";
    public const HEADER_BODY_DELIMITER = "\r\n\r\n";

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
    protected $contentLength = 0;

    /**
     * {@inheritdoc}
     */
    public function setStream(DuplexStreamInterface $stream)
    {
        $this->connection = $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function push(string $chunk)
    {
        $this->buffer .= $chunk;
        $this->length += strlen($chunk);
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(): iterable
    {
        // parse http request header
        if (null === $this->request && false !== ($pos = strpos($this->buffer, static::HEADER_BODY_DELIMITER))) {
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

        // collect request body.
        if (null !== $this->request) {
            if (null === $this->contentLength) {
                throw new InvalidHeaderException('The chunk model is not supported now.');
            } else if ($this->length >= ($length = $this->contentLength + 4)) {
                yield $this->captureRequestBody($length);
                $this->resetState();
            }
        }
    }

    protected function captureRequestBody(int $length)
    {
        $content = ltrim(substr($this->buffer, 0, $length), static::CRLF);
        // reset buffer state
        $this->buffer = substr($this->buffer, $length);
        $this->length -= $length;

        $body = new BufferStream();
        $body->write($content);
        return $this->request->withBody($body);
    }

    protected function resetState()
    {
        $this->request = null;
        $this->contentLength = null;

        // Maybe reset buffer contains a full request.
        if (false !== strpos($this->buffer, "\r\n\r\n")) {
            $this->evaluate();
        }
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

        $headers = Rfc7230::parseHeaders(ltrim(strstr($header, static::CRLF), static::CRLF) . static::CRLF);

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