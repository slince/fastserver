<?php

namespace FastServer;

use React\Promise\Deferred;

class BufferStream
{
    /**
     * @var string
     */
    protected $buffer;

    /**
     * Buffer length.
     * @var int
     */
    protected $length = 0;

    /**
     * Push incoming data to stream.
     *
     * @param string $chunk
     */
    public function push(string $chunk)
    {
        $this->buffer .= $chunk;
        $this->length += strlen($chunk);
    }

    public function readUntil(string $char)
    {
        $deferred = new Deferred();
        $callable = function () {

        };
        return $deferred->promise();
    }

    public function readBytes(int $length)
    {
        $deferred = new Deferred();
        return $deferred->promise();
    }
}