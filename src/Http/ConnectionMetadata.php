<?php

declare(strict_types=1);

/*
 * This file is part of the fastserver/fastserver package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @return int
     */
    public function getRequests(): int
    {
        return $this->requests;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}