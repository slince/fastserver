<?php

namespace Waveman\Server\Command;

use Waveman\Channel\CommandInterface;

final class WorkerCloseCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'WORKER_CLOSE';
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandKey(): string
    {
        return $this->getCommandId();
    }
}