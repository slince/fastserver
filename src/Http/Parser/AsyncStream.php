<?php

namespace Viso\Http\Parser;

use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;

class AsyncStream implements StreamInterface
{
    private ReadableStreamInterface $stream;

    public function __construct(ReadableStreamInterface $stream)
    {
        $this->stream = $stream;
    }

    public function __toString()
    {
        return '';
    }

    public function close()
    {
        $this->stream->close();
    }

    public function detach()
    {

    }

    public function getSize()
    {
    }

    public function tell()
    {
    }

    public function eof()
    {
    }

    public function isSeekable()
    {
    }

    public function seek(int $offset, int $whence = SEEK_SET)
    {
    }

    public function rewind()
    {
    }

    public function isWritable()
    {
        return $this->stream->isWritable();
    }

    public function write(string $string)
    {
        // TODO: Implement write() method.
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