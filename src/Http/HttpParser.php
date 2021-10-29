<?php

namespace FastServer\Http;

use FastServer\ParserInterface;

class HttpParser implements ParserInterface
{
    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * Buffer length.
     * @var int
     */
    protected $length = 0;

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
    public function evaluate(): array
    {
        $messages = [];
    }
}