<?php

declare(strict_types=1);

/*
 * This file is part of the waveman/waveman package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Waveman\Http\Parser;

use GuzzleHttp\Psr7\BufferStream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Stream\WritableStreamInterface;
use Waveman\Server\Parser\WriterInterface;

final class HttpEmitter implements WriterInterface
{
    public const MAX_BUFFER_LENGTH = 8192;

    /**
     * @var WritableStreamInterface
     */
    protected WritableStreamInterface $stream;

    public function __construct(WritableStreamInterface $stream)
    {
        $this->stream = $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function write($response, $request = null): void
    {
        $this->emit($response, $request);
    }

    /**
     * Emit a psr7 response.
     *
     * @param ResponseInterface $response
     * @param ServerRequestInterface|null $request
     */
    public function emit(ResponseInterface $response, ?ServerRequestInterface $request = null): void
    {
        $response = $this->handleResponse($response, $request);

        $this->emitStatusLine($response);
        $this->emitHeaders($response);

        $this->writeLine(HttpParser::CRLF, false);

        $range = $this->parseContentRange($response->getHeaderLine('Content-Range'));

        if (null === $range || 'bytes' !== $range[0]) {
            $this->emitBody($response);
            return;
        }

        $this->emitBodyRange($range, $response);
    }

    /**
     * @link https://github.com/reactphp/http/blob/master/src/Io/StreamingServer.php#L232
     *
     * @param ResponseInterface $response
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     */
    protected function handleResponse(ResponseInterface $response, ?ServerRequestInterface $request = null): ResponseInterface
    {
        // return early and close response body if connection is already closed
        $body = $response->getBody();
        $code = $response->getStatusCode();

        $method = 'GET';
        $version = $response->getProtocolVersion();

        if (null !== $request) {
            $method = $request->getMethod();
            // assign HTTP protocol version from request automatically
            $version = $request->getProtocolVersion();
            $response = $response->withProtocolVersion($version);
        }


        // assign default "X-Powered-By" header automatically
        if (!$response->hasHeader('Server')) {
            $response = $response->withHeader('Server', 'Waveman/1');
        } elseif ($response->getHeaderLine('Server') === ''){
            $response = $response->withoutHeader('Server');
        }

        // assign default "Date" header from current time automatically
        if (!$response->hasHeader('Date')) {
            // IMF-fixdate  = day-name "," SP date1 SP time-of-day SP GMT
            $response = $response->withHeader('Date', gmdate('D, d M Y H:i:s') . ' GMT');
        } elseif ($response->getHeaderLine('Date') === ''){
            $response = $response->withoutHeader('Date');
        }

        // assign "Content-Length" and "Transfer-Encoding" headers automatically
        $chunked = false;
        if (($method === 'CONNECT' && $code >= 200 && $code < 300) || ($code >= 100 && $code < 200) || $code === 204) {
            // 2xx response to CONNECT and 1xx and 204 MUST NOT include Content-Length or Transfer-Encoding header
            $response = $response->withoutHeader('Content-Length')->withoutHeader('Transfer-Encoding');
        } elseif ($body->getSize() !== null) {
            // assign Content-Length header when using a "normal" buffered body string
            $response = $response->withHeader('Content-Length', (string)$body->getSize())->withoutHeader('Transfer-Encoding');
        } elseif (!$response->hasHeader('Content-Length') && $version === '1.1') {
            // assign chunked transfer-encoding if no 'content-length' is given for HTTP/1.1 responses
            $response = $response->withHeader('Transfer-Encoding', 'chunked');
            $chunked = true;
        } else {
            // remove any Transfer-Encoding headers unless automatically enabled above
            $response = $response->withoutHeader('Transfer-Encoding');
        }

        // assign "Connection" header automatically
        if ($code === 101) {
            // 101 (Switching Protocols) response uses Connection: upgrade header
            $response = $response->withHeader('Connection', 'upgrade');
        }

        // response to HEAD and 1xx, 204 and 304 responses MUST NOT include a body
        // exclude status 101 (Switching Protocols) here for Upgrade request handling above
        if ($method === 'HEAD' || $code === 100 || ($code > 101 && $code < 200) || $code === 204 || $code === 304) {
            $response = $response->withBody(new BufferStream());
        }

        return $response;
    }

    /**
     * Emit the status line.
     *
     * Emits the status line using the protocol version and status code from
     * the response; if a reason phrase is available, it, too, is emitted.
     *
     * It is important to mention that this method should be called after
     * `emitHeaders()` in order to prevent PHP from changing the status code of
     * the emitted response.
     */
    protected function emitStatusLine(ResponseInterface $response): void
    {
        $reasonPhrase = $response->getReasonPhrase();
        $statusCode   = $response->getStatusCode();

        $this->writeLine(sprintf(
            'HTTP/%s %d%s',
            $response->getProtocolVersion(),
            $statusCode,
            $reasonPhrase ? ' ' . $reasonPhrase : ''
        ));
    }

    /**
     * Emit response headers.
     *
     * Loops through each header, emitting each; if the header value
     * is an array with multiple values, ensures that each is sent
     * in such a way as to create aggregate headers (instead of replace
     * the previous).
     */
    protected function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $header => $values) {
            $name = $this->filterHeader($header);
            foreach ($values as $value) {
                $this->writeLine(sprintf(
                    '%s: %s',
                    $name,
                    $value
                ));
            }
        }
    }

    protected function filterHeader(string $header): string
    {
        return ucwords($header, '-');
    }

    /**
     * Emit the message body.
     */
    protected function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }
        if (!$body->isReadable()) {
            $this->writeLine((string)$body) ;
            return;
        }

        while (!$body->eof()) {
            $this->writeLine($body->read(self::MAX_BUFFER_LENGTH), false);
        }
        $this->writeLine('');
    }

    /**
     * Emit a range of the message body.
     *
     * @param array $range
     * @param ResponseInterface $response
     */
    protected function emitBodyRange(array $range, ResponseInterface $response): void
    {
        [, $first, $last] = $range;

        $body = $response->getBody();

        $length = $last - $first + 1;

        if ($body->isSeekable()) {
            $body->seek($first);

            $first = 0;
        }

        if (! $body->isReadable()) {
            $this->writeLine(substr($body->getContents(), $first, $length));
            return;
        }

        $remaining = $length;

        while ($remaining >= self::MAX_BUFFER_LENGTH && ! $body->eof()) {
            $contents   = $body->read(self::MAX_BUFFER_LENGTH);
            $remaining -= strlen($contents);

            $this->writeLine($contents);
        }

        if ($remaining > 0 && ! $body->eof()) {
            $this->writeLine($body->read($remaining));
        }
    }

    /**
     * Parse content-range header
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.16
     *
     * @return null|array [unit, first, last, length]; returns null if no
     *     content range or an invalid content range is provided
     */
    protected function parseContentRange(string $header): ?array
    {
        if (! preg_match('/(?P<unit>[\w]+)\s+(?P<first>\d+)-(?P<last>\d+)\/(?P<length>\d+|\*)/', $header, $matches)) {
            return null;
        }

        return [
            $matches['unit'],
            (int) $matches['first'],
            (int) $matches['last'],
            $matches['length'] === '*' ? '*' : (int) $matches['length'],
        ];
    }

    protected function writeLine(string $data, bool $newLine = true): void
    {
        if ($newLine) {
            $data .= HttpParser::CRLF;
        }
        $this->stream->write($data);
    }
}