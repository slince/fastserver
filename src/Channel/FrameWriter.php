<?php

namespace Viso\Channel;

use React\Stream\WritableStreamInterface;
use Viso\Parser\WriterInterface;

class FrameWriter implements WriterInterface
{
    private WritableStreamInterface $stream;

    /**
     * @param WritableStreamInterface $stream
     */
    public function __construct(WritableStreamInterface $stream)
    {
        $this->stream = $stream;
    }

    public function write(mixed $frame, mixed $request = null)
    {

    }
}