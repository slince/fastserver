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

namespace FastServer;

use React\Socket\ConnectionInterface;

class ConnectionMetadata
{
    /**
     * @var ConnectionInterface
     */
    protected ConnectionInterface $connection;

    /**
     * Created time.
     *
     * @var \DateTimeInterface
     */
    protected \DateTimeInterface $createdAt;

    /**
     * Update time.
     *
     * @var \DateTimeInterface
     */
    protected \DateTimeInterface $updatedAt;

    /**
     * total requests of the connection.
     * @var int
     */
    protected int $requests = 0;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->createdAt = $this->updatedAt = new \DateTime();
    }

    /**
     * Incr request counter.
     */
    public function incrRequest(): void
    {
        $this->updatedAt = new \DateTime();
        $this->requests += 1;
    }

    /**
     * returns the connection.
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Gets the total requests.
     *
     * @return int
     */
    public function getRequests(): int
    {
        return $this->requests;
    }

    /**
     * Gets create time.
     *
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Gets the update time.
     *
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Alive seconds.
     *
     * @return int
     */
    public function getAliveSeconds(): int
    {
        return time() - $this->createdAt->getTimestamp();
    }
}