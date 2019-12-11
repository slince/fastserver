<?php

namespace FastServer\Http;

use FastServer\TcpServer;

class HttpServer extends TcpServer
{
    public function createParser()
    {
        return new RequestHeaderParser();
    }
}