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

namespace Waveman\Server;

use React\Socket\ServerInterface as Socket;

interface ServerInterface
{
    /**
     * Add an event listener.
     *
     * @param string $event
     * @param callable $listener
     */
    public function on(string $event, callable $listener): void;

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
    public function serve(): void;

    /**
     * Close the server and exit.
     * @param bool $graceful
     * @return void
     */
    public function stop(bool $graceful = true): void;

    /**
     * Returns the socket instance.
     * 
     * @return Socket
     */
    public function getSocket(): Socket;
}