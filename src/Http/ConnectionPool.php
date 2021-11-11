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

class ConnectionPool implements \IteratorAggregate, \Countable
{
    /**
     * @var \SplObjectStorage
     */
    protected $connections;

    public function __construct()
    {
        $this->connections = new \SplObjectStorage();
    }

    public function add(ConnectionInterface $connection)
    {
        $this->connections->attach($connection, new ConnectionMetadata($connection));
    }

    public function remove(ConnectionInterface $connection)
    {
        $this->connections->detach($connection);
    }

    public function getMetadata(ConnectionInterface $connection): ConnectionMetadata
    {
        return $this->connections->offsetGet($connection);
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