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

namespace Waveman\Channel;

interface ChannelInterface
{
    /**
     * Writes a request for the given command over the connection.
     *
     * @param CommandInterface $command Command instance.
     */
    public function send(CommandInterface $command): void;

    /**
     * Add a listener to listen command.
     *
     * @param callable $callback
     */
    public function listen(callable $callback): void;

    /**
     * Checks whether channel support the command.
     *
     * @param CommandInterface $command
     * @return bool
     */
    public function supports(CommandInterface $command): bool;
}