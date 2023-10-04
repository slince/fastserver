<?php

namespace Waveman\Server\Command;

use Waveman\Server\ConnectionDescriptor;
use Waveman\Server\ConnectionPool;

final class WorkerConnectionsCommand extends WorkerCommand
{
    private ConnectionPool $connections;

    public function __construct(int $workerId, ConnectionPool $connections)
    {
        parent::__construct($workerId);
        $this->connections = $connections;
    }

    public function getConnectionDescriptors(): array
    {
        $descriptors = [];
        foreach ($this->connections as $metadata) {
            $descriptors[] = ConnectionDescriptor::fromConnectionMetadata($metadata);
        }
        return $descriptors;
    }

    public function getCommandId(): string
    {
        return 'WORKER_CONNECTIONS';
    }
}