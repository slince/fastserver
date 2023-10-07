<?php

namespace Waveman\Server\Command;

use Waveman\Channel\CommandInterface;

final class NopCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'NOP';
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandKey(): string
    {
        return $this->getCommandId();
    }
}