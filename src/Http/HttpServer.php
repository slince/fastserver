<?php

namespace FastServer\Http;

use FastServer\Server\AbstractServer;

class HttpServer extends AbstractServer
{
    /**
     * @var resource
     */
    protected $socket;


    public function serve()
    {
        $this->socket = $this->createSocket(
            "tcp://{$this->options['address']}"
        );
    }
}