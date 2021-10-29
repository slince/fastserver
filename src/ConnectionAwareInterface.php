<?php

namespace FastServer;

use React\Socket\ConnectionInterface;

interface ConnectionAwareInterface
{
    public function setConnection(ConnectionInterface $connection);
}