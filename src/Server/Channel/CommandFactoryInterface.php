<?php

namespace Waveman\Server\Channel;

use Waveman\Server\Channel\Command\CommandInterface;

interface CommandFactoryInterface
{
    /**
     * Creates the command from message.
     *
     * @param Message $message
     * @return CommandInterface
     */
    public function createCommand(Message $message): CommandInterface;
}