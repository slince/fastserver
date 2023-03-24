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

use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface as SocketServer;

interface ServerInterface
{
    /**
     * Configure the server.
     *
     * @param array $options
     */
    public function configure(array $options);

    /**
     * Add an event listener.
     *
     * @param string $event
     * @param callable $listener
     */
    public function on(string $event, callable $listener);

    /**
     * Gets the specific option.
     *
     * @param string $name
     * @return mixed
     */
    public function getOption(string $name): mixed;

    /**
     * Start the server.
     */
    public function serve();

    /**
     * Close the server and exit.
     * @return void
     */
    public function quit(): void;

    /**
     * {@internal}
     */
    public function handleConnection(ConnectionInterface $connection);

    /**
     * {@internal}
     */
    public function handleError(\Exception $e);

    /**
     * {@internal}
     */
    public function getSocket();

    /**
     * Creates a raw socket resource.
     */
    public function createSocket();

    /**
     * Create a socket server.
     *
     * @param resource $socket
     * @return SocketServer
     */
    public function createSocketServer($socket, LoopInterface $loop): SocketServer;
}