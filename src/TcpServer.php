<?php

namespace FastServer;

use React\Socket\Connection;

abstract class TcpServer extends AbstractServer
{
    protected $parser;

    public function handleConnection(Connection $connection)
    {
        $this->parser = $this->createParser();
        $this->parser->handleConnection($connection);
    }

    /**
     * @return ProtocolParserInterface
     */
    abstract function createParser();
}