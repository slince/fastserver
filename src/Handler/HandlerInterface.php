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

namespace FastServer\Handler;

use FastServer\Bridge\Command\CommandInterface;
use FastServer\Bridge\BridgeInterface;

interface HandlerInterface
{
    /**
     * Handling the command.
     *
     * @param CommandInterface $command
     * @param BridgeInterface $connection
     */
    public function handle(CommandInterface $command, BridgeInterface $connection);

    /**
     * Returns whether this class supports the given command.
     *
     * @param CommandInterface $command
     * @return bool
     */
    public function supports(CommandInterface $command): bool;
}