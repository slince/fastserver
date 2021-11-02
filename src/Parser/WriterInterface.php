<?php

namespace FastServer\Parser;

interface WriterInterface
{
    /**
     * Writes message to the stream.
     *
     * @param mixed $response response message.
     * @param mixed $request request message.
     */
    public function write($response, $request);
}