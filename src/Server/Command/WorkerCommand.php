<?php

namespace Waveman\Server\Command;

use Waveman\Channel\CommandInterface;

abstract class WorkerCommand implements CommandInterface
{
    private int $workerId;

    public function __construct(int $workerId)
    {
        $this->workerId = $workerId;
    }

    /**
     * Return the worker id
     * @return int
     */
    public function getWorkerId(): int
    {
        return $this->workerId;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandKey(): string
    {
        return $this->getCommandId();
    }
}