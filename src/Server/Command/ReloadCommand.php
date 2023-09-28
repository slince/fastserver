<?php

namespace Waveman\Server\Command;

use Waveman\Server\Channel\CommandInterface;

class ReloadCommand implements CommandInterface
{
    public function getCommandId(): string
    {
        return 'RELOAD';
    }

    public function getCommandKey(): string
    {
        return $this->getCommandId();
    }
}