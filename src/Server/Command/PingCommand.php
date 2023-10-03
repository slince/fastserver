<?php

namespace Waveman\Server\Command;

final class PingCommand extends WorkerCommand
{
    public function getCommandId(): string
    {
        return 'PING';
    }
}