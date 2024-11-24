<?php

namespace Viso\Http\Parser;

use Evenement\EventEmitter;

class LimitedLengthBodyParser extends EventEmitter implements BodyParserInterface
{
    private int $length;

    private string $buffer;

    public function __construct(int $length)
    {
        $this->length = $length;
    }

    public function handle(string $chunk): void
    {
        $this->buffer .= $chunk;
        if (strlen($this->buffer) >= $this->length) {
            $this->emit('body', [substr($this->buffer, $this->length)]);
        }
    }
}