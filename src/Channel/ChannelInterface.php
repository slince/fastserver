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

namespace Viso\Channel;

interface ChannelInterface
{
    /**
     * Writes a request for the given command over the connection.
     *
     * @param Frame $frame
     */
    public function send(Frame $frame): void;

    /**
     * Add a listener to listen command.
     *
     * @param callable $listener
     * @param bool $override
     */
    public function listen(callable $listener, bool $override = false);
}