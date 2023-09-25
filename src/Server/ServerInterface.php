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

namespace Waveman\Server;

interface ServerInterface
{
    /**
     * Configure the server.
     *
     * @param array $options
     */
    public function configure(array $options): void;

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
     * @return void
     */
    public function stop(): void;

    /**
     * {@internal}
     */
    public function createSocket();

    /**
     * {@internal}
     */
    public function getSocket();
}