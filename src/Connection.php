<?php

namespace FastServer;

use React\Socket\ConnectionInterface;
use React\Stream\WritableStreamInterface;

class Connection implements ConnectionInterface
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }


    public function isReadable()
    {
        // TODO: Implement isReadable() method.
    }

    public function pause()
    {
        // TODO: Implement pause() method.
    }

    public function resume()
    {
        // TODO: Implement resume() method.
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        // TODO: Implement pipe() method.
    }

    public function close()
    {
        // TODO: Implement close() method.
    }
}