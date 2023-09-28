<?php

namespace Waveman\Server\Command;

use Waveman\Server\Channel\CommandFactoryInterface;
use Waveman\Server\Channel\CommandInterface;
use Waveman\Server\Channel\Message;

final class CommandFactory implements CommandFactoryInterface
{
    public function createMessage(CommandInterface $command): Message
    {
        $message = new Message();
    }

    public function createCommand(Message $message): CommandInterface
    {
    }
}