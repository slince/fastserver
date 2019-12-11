<?php

namespace FastServer;

use React\Socket\Connection;

interface ProtocolParserInterface
{

    public function handleConnection(Connection $connection);
}