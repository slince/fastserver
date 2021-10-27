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
use React\Socket\ServerInterface as Socket;

interface ServerInterface
{
    /**
     * Add an event listener.
     *
     * @param string $event
     * @param callable $listener
     */
    public function on($event, callable $listener);

    /**
     * Configure the server.
     *
     * @param array $options
     */
    public function configure(array $options);

    /**
     * Gets the specific option.
     *
     * @param string $name
     * @return mixed
     */
    public function getOption(string $name);

    /**
     * Start the server.
     */
    public function serve();

    /**
     * Pause service.
     */
    public function pause();

    /**
     * Resume service.
     */
    public function resume();

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
    public function getSocket(): Socket;
}