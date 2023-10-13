<?php

declare(strict_types=1);

/*
 * This file is part of the waveman/waveman package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Waveman\Cluster\Command;

use Waveman\Cluster\ConnectionDescriptor;

final class WorkerConnectionsCommand extends WorkerCommand
{
    /**
     * @var array<ConnectionDescriptor>
     */
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