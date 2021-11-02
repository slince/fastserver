<?php

namespace FastServer\Http;

use React\Socket\ConnectionInterface;

class ConnectionPool
{
    protected $connections;

    public function __construct()
    {
        $this->connections = new \SplObjectStorage();
    }

    public function add(ConnectionInterface $connection)
    {
        $this->connections->attach($connection, new ConnectionMetadata($connection));
    }

    public function remove(ConnectionInterface $connection)
    {
        $this->connections->detach($connection);
    }

    public function getMetadata(ConnectionInterface $connection): ConnectionMetadata
    {
        return $this->connections->offsetGet($connection);
    }
}