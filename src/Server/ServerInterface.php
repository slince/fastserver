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

namespace Viso\Server;

use Psr\Log\LoggerInterface;
use Viso\Cluster\ConnectionPool;

interface ServerInterface
{
    /**
     * Add an event listener.
     *
     * @param string $event
     * @param callable $listener
     */
    public function on($event, callable $listener): void;

    /**
     * Start the server.
     */
    public function serve(): void;

    /**
     * Close the server and exit.
     * @param bool $graceful
     * @return void
     */
    public function close(bool $graceful = true): void;

    /**
     * Returns the connection pool of the server.
     *
     * @return ConnectionPool
     */
    public function getConnections(): ConnectionPool;

    /**
     * Return the logger instance.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface;
}