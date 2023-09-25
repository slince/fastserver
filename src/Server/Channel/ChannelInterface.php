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

namespace Waveman\Server\Channel;

use Waveman\Server\Channel\Command\CommandInterface;

interface ChannelInterface
{
    /**
     * Writes a request for the given command over the connection.
     *
     * @param CommandInterface $command Command instance.
     */
    public function executeCommand(CommandInterface $command): void;

    /**
     * Add a listener to listen message.
     *
     * @param callable $callback
     */
    public function listen(callable $callback): void;
}