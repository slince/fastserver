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
namespace Viso\Server\Command;

use Viso\Cluster\Command\PayloadCommandInterface;
use Viso\Cluster\Command\WorkerCommand;
use Viso\Server\ConnectionDescriptor;

final class ConnectionsCommand extends WorkerCommand implements PayloadCommandInterface
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
     * @return array<ConnectionDescriptor>
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'CONNECTIONS';
    }

    /**
     * {@inheritdoc}
     */
    public function getPayload(): array
    {
        return ['worker_id' => $this->getWorkerId(), 'connections' => $this->connections];
    }

    /**
     * {@inheritdoc}
     */
    public static function create(mixed $payload): static
    {
        return new ConnectionsCommand($payload['worker_id'], array_map(fn($item)=> new ConnectionDescriptor(...$item), $payload['connections']));
    }
}