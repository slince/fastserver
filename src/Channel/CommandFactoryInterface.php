<?php

namespace Waveman\Channel;

interface CommandFactoryInterface
{
    /**
     * Creates the message instance.
     * @param CommandInterface $command
     * @return Frame
     */
    public function createMessage(CommandInterface $command): Frame;

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