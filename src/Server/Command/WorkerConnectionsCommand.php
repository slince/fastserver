<?php

namespace Waveman\Server\Command;

use Waveman\Cluster\Command\WorkerCommand;

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