<?php

namespace FastServer\Http;

use React\Socket\ConnectionInterface;

class ConnectionMetadata
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * Created time.
     *
     * @var \DateTimeInterface
     */
    protected $createdAt;

    /**
     * Update time.
     *
     * @var \DateTimeInterface
     */
    protected $updatedAt;

    /**
     * total requests of the connection.
     * @var int
     */
    protected $requests = 0;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->createdAt = $this->updatedAt = new \DateTime();
    }

    public function incrRequest()
    {
        $this->updatedAt = new \DateTime();
        $this->requests += 1;
    }
}