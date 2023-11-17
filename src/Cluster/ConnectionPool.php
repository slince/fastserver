<?php

declare(strict_types=1);

/*
 * This file is part of the waveman/waveman package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Viso\Cluster;

use React\Socket\ConnectionInterface;

final class ConnectionPool implements \IteratorAggregate, \Countable
{
    /**
     * @var \SplObjectStorage<ConnectionInterface, ConnectionMetadata>
     */
    protected \SplObjectStorage $connections;

    public function __construct()
    {
        $this->connections = new \SplObjectStorage();
    }

    public function add(ConnectionInterface $connection): void
    {
        $this->connections->attach($connection, new ConnectionMetadata($connection));
    }

    public function remove(ConnectionInterface $connection): void
    {
        $this->connections->detach($connection);
    }

    public function getMetadata(ConnectionInterface $connection): ConnectionMetadata
    {
        return $this->connections->offsetGet($connection);
    }

    public function close(): void
    {
        foreach ($this->connections as $connection => $_) {
            $connection->end();
        }
    }

    public function getIterator(): \Iterator
    {
        return $this->connections;
    }

    public function count(): int
    {
        return count($this->connections);
    }
}