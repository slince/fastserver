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

namespace Waveman\Server\Command\Handler;

use Waveman\Channel\ChannelInterface;
use Waveman\Channel\CommandInterface;

interface HandlerInterface
{
    /**
     * Handling the command.
     *
     * @param CommandInterface $command
     * @param ChannelInterface $connection
     */
    public function handle(CommandInterface $command, ChannelInterface $connection);

    /**
     * Returns whether this class supports the given command.
     *
     * @param CommandInterface $command
     * @return bool
     */
    public function supports(CommandInterface $command): bool;
}