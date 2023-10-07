<?php

namespace Waveman\Server\Command;

use Waveman\Channel\CommandInterface;

class HeartbeatCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'HEARTBEAT';
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandKey(): string
    {
        return $this->getCommandId();
    }
}