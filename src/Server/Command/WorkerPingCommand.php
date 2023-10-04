<?php

namespace Waveman\Server\Command;

final class WorkerPingCommand extends WorkerCommand
{
    public function getCommandId(): string
    {
        return 'PING';
    }
}