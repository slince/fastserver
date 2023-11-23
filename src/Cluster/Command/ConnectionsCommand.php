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
namespace Viso\Cluster\Command;

use Viso\Cluster\ConnectionDescriptor;

final class ConnectionsCommand extends WorkerCommand
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
        return 'CONNECTIONS';
    }
}