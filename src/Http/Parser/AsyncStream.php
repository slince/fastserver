<?php

namespace Viso\Http\Parser;

use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;

/**
 * @internal
 */
final class AsyncStream implements StreamInterface
{
    private ReadableStreamInterface $stream;

    public function __construct(ReadableStreamInterface $stream)
    {
        $this->stream = $stream;
    }

    public function __toString()
    {
        throw new \BadMethodCallException();
    }

    public function close(): void
    {
        $this->stream->close();
    }

    public function detach()
    {
        throw new \BadMethodCallException();
    }

    public function getSize()
    {
        throw new \BadMethodCallException();
    }

    public function tell()
    {
        throw new \BadMethodCallException();
    }

    public function eof()
    {
        throw new \BadMethodCallException();
    }

    public function isSeekable(): bool
    {
        throw new \BadMethodCallException();
    }

    public function seek(int $offset, int $whence = SEEK_SET)
    {
        throw new \BadMethodCallException();
    }

    public function rewind()
    {
        throw new \BadMethodCallException();
    }

    public function isWritable()
    {
        return $this->stream->isWritable();
    }

    public function write(string $string)
    {
        throw new \BadMethodCallException();
    }

    public function isReadable()
    {
        return $this->stream->isReadable();
    }

    public function read(int $length)
    {
        throw new \BadMethodCallException();
    }

    public function getContents()
    {
        throw new \BadMethodCallException();
    }

    public function getMetadata(?string $key = null)
    {
        throw new \BadMethodCallException();
    }
}