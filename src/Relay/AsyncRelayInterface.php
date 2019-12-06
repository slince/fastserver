<?php

namespace FastServer\Relay;

use Amp\Promise;

interface AsyncRelayInterface extends RelayInterface
{
    public function sendAsync($payload, int $flags) :Promise;

    public function receiveAsync() :Promise;
}