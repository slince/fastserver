<?php

namespace FastServer;

use React\Socket\ConnectionInterface;

class TcpServer extends AbstractServer
{
    public function handleConnection(ConnectionInterface $connection)
    {
        $this->emit('connection', [$connection]);
    }
}