<?php

namespace Waveman\Server\Channel;

interface CommandFactoryInterface
{
    /**
     * Creates the message instance.
     * @param CommandInterface $command
     * @return Message
     */
    public function createMessage(CommandInterface $command): Message;

    /**
     * Creates the command from message.
     *
     * @param Message $message
     * @return CommandInterface
     */
    public function createCommand(Message $message): CommandInterface;
}