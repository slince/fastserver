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

use FastServer\BufferStream;
use GuzzleHttp\Psr7\ServerRequest;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;

class HttpParser
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    protected $buffer;

    public function __construct(BufferStream $buffer)
    {
        $this->buffer = $buffer;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(): Promise
    {
        $deferred = new Deferred();

        $this->buffer->readUntil("\r\n\r\n")->done(function (string $header) use ($deferred) {

            if (!\preg_match('#^(?<method>[^ ]+) (?<target>[^ ]+) HTTP/(?<version>\d\.\d)#m', $header, $start)) {
                throw new \InvalidArgumentException('Unable to parse invalid request-line');
            }

            // only support HTTP/1.1 and HTTP/1.0 requests
            if ($start['version'] !== '1.1' && $start['version'] !== '1.0') {
                throw new \InvalidArgumentException('Received request with invalid protocol version', 505);
            }

            $headers = Rfc7230::parseHeaders($header);

            // format all header fields into associative array
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
                    throw new \InvalidArgumentException('CONNECT method MUST use authority-form request target');
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
                        throw new \InvalidArgumentException('Invalid absolute-form request-target');
                    }

                    $uri = $start['target'];
                }
            }

            $deferred->resolve(new ServerRequest(
                $start['method'],
                $uri,
                $headers,
                null,
                $start['version']
            ));
        });

        return $deferred->promise();
    }
}