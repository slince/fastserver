<?php

namespace FastServer;

use React\Socket\ConnectionInterface;

interface ServerInterface
{
    public function on($eventName, callable $listener);

    public function configure(array $options);

    public function serve();

    public function handleConnection(ConnectionInterface $connection);
}