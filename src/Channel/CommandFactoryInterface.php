<?php

namespace Waveman\Channel;

interface CommandFactoryInterface
{
    /**
     * Creates the message instance.
     * @param CommandInterface $command
     * @return Message
     */
    public function createMessage(CommandInterface $command): Message;

    /**
     * Checks whether support the command.
     *
     * @param CommandInterface $command
     * @return bool
     */
    public function supportCommand(CommandInterface $command): bool;

    /**
     * Creates the command from message.
     *
     * @param Message $message
     * @return CommandInterface
     */
    public function createCommand(Message $message): CommandInterface;
}