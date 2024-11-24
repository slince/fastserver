<?php

namespace Viso\Http\Parser;

use Evenement\EventEmitter;

class ChunkedBodyParser extends EventEmitter implements BodyParserInterface
{
    public function handle(string $chunk): void
    {
        
    }
}