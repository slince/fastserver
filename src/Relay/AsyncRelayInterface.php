<?php

namespace FastServer\Relay;

use Amp\Promise;

interface AsyncRelayInterface
{
    public function sendAsync($payload, int $flags) :Promise;

    public function receiveAsync() :Promise;
}