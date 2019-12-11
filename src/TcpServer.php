<?php

namespace FastServer;

use React\Socket\ConnectionInterface;

class TcpServer extends AbstractServer
{
    /**
     * @var ProtocolParserInterface
     */
    protected $parser;

    public function __construct(ProtocolParserInterface $parser)
    {
        $this->parser = $parser;
        parent::__construct();
    }

    public function handleConnection(ConnectionInterface $connection)
    {
        $this->parser->handleConnection($connection);
    }
}