<?php

namespace Viso\Http\Parser;

use Evenement\EventEmitterInterface;

interface BodyParserInterface extends EventEmitterInterface
{
    public function handle(string $chunk): void;
}