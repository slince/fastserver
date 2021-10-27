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

namespace FastServer\Connection;

use Evenement\EventEmitterInterface;
use FastServer\Command\CommandInterface;

interface ConnectionInterface extends EventEmitterInterface
{
    /**
     * Closes the connection to FastServerd.
     *
     * @param bool $force
     */
    public function disconnect(bool $force = false);

    /**
     * Writes a request for the given command over the connection.
     *
     * @param CommandInterface $command Command instance.
     */
    public function executeCommand(CommandInterface $command);
}