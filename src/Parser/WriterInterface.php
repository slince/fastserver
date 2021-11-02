<?php

namespace FastServer\Parser;

interface WriterInterface
{
    /**
     * Writes message to the stream.
     *
     * @param mixed $message response message.
     */
    public function write($message);
}