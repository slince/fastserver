<?php

namespace FastServer;

use Evenement\EventEmitterInterface;
use React\Socket\ConnectionInterface;

interface ProtocolParserInterface extends EventEmitterInterface
{
    public function handle(ConnectionInterface $connection);
}