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
namespace Viso\Cluster\Command;

use Viso\Channel\Frame;

interface CommandFactoryInterface
{
    /**
     * Creates the frame instance.
     * @param CommandInterface $command
     * @return Frame
     */
    public function createFrame(CommandInterface $command): Frame;

    /**
     * Checks whether support the command.
     *
     * @param CommandInterface $command
     * @return bool
     */
    public function supportCommand(CommandInterface $command): bool;

    /**
     * Creates the command from frame.
     *
     * @param Frame $frame
     * @return CommandInterface
     */
    public function createCommand(Frame $frame): CommandInterface;
}