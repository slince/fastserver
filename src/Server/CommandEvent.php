<?php

namespace Waveman\Server;

use Waveman\Channel\CommandInterface;
use Waveman\Server\Worker\Worker;

class CommandEvent
{
    private CommandInterface $command;
    private ?Worker $worker;

    public function __construct(CommandInterface $command, ?Worker $worker)
    {
        $this->command = $command;
        $this->worker = $worker;
    }

    public function getCommand(): CommandInterface
    {
        return $this->command;
    }

    public function getWorker(): ?Worker
    {
        return $this->worker;
    }
}