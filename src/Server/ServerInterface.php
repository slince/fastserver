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
     * Run the server.
     *
     * @return void
     */
    public function run(): void;

    /**
     * Close the server and exit.
     * @param bool $graceful
     * @return void
     */
    public function stop(bool $graceful = true): void;
}