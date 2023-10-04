<?php

namespace Waveman\Server\Command;

final class WorkerConnectionsCommand extends WorkerCommand
{
    private array $connections;

    public function __construct(int $workerId, array $connections)
    {
        parent::__construct($workerId);
        $this->connections = $connections;
    }

    /**
     * @return array
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    public function getCommandId(): string
    {
        return 'WORKER_CONNECTIONS';
    }
}