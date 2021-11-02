<?php

namespace FastServer\Http;

use FastServer\Parser\WriterInterface;
use Psr\Http\Message\ResponseInterface;
use React\Stream\WritableStreamInterface;

final class HttpEmitter implements WriterInterface
{
    public const MAX_BUFFER_LENGTH = 8192;

    /**
     * @var WritableStreamInterface
     */
    protected $stream;

    public function __construct(WritableStreamInterface $stream)
    {
        $this->stream = $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function write($message)
    {
        $this->emit($message);
    }

    /**
     * Emit a psr7 response.
     *
     * @param ResponseInterface $response
     */
    public function emit(ResponseInterface $response)
    {
        $this->emitStatusLine($response);
        $this->emitHeaders($response);

        $range = $this->parseContentRange($response->getHeaderLine('Content-Range'));

        if (null === $range || 'bytes' !== $range[0]) {
            $this->emitBody($response);
            return;
        }

        $this->emitBodyRange($range, $response);
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
    protected function emitStatusLine(ResponseInterface $response)
    {
        $reasonPhrase = $response->getReasonPhrase();
        $statusCode   = $response->getStatusCode();

        $this->writeChunk(sprintf(
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
    protected function emitHeaders(ResponseInterface $response)
    {
        foreach ($response->getHeaders() as $header => $values) {
            $name  = $this->filterHeader($header);
            foreach ($values as $value) {
                $this->writeChunk(sprintf(
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
    protected function emitBody(ResponseInterface $response)
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        if (! $body->isReadable()) {
            $this->writeChunk($body) ;
            return;
        }

        while (! $body->eof()) {
            $this->writeChunk($body->read(self::MAX_BUFFER_LENGTH));
        }
    }

    /**
     * Emit a range of the message body.
     *
     * @param array $range
     * @param ResponseInterface $response
     */
    protected function emitBodyRange(array $range, ResponseInterface $response)
    {
        [, $first, $last] = $range;

        $body = $response->getBody();

        $length = $last - $first + 1;

        if ($body->isSeekable()) {
            $body->seek($first);

            $first = 0;
        }

        if (! $body->isReadable()) {
            $this->writeChunk(substr($body->getContents(), $first, $length));
            return;
        }

        $remaining = $length;

        while ($remaining >= self::MAX_BUFFER_LENGTH && ! $body->eof()) {
            $contents   = $body->read(self::MAX_BUFFER_LENGTH);
            $remaining -= strlen($contents);

            $this->writeChunk($contents);
        }

        if ($remaining > 0 && ! $body->eof()) {
            $this->writeChunk($body->read($remaining));
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

    protected function writeChunk(string $data)
    {
        $this->stream->write($data);
    }
}