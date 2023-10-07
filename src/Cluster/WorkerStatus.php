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

namespace Waveman\Cluster;

final class WorkerStatus implements \JsonSerializable
{
    private int $pid;
    private string $listening;
    private int $memoryUsage;
    private int $connections;

    /**
     * @param int $pid
     * @param string $listening
     * @param int $memoryUsage
     * @param int $connections
     */
    public function __construct(int $pid, string $listening, int $memoryUsage, int $connections)
    {
        $this->pid = $pid;
        $this->listening = $listening;
        $this->memoryUsage = $memoryUsage;
        $this->connections = $connections;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getListening(): string
    {
        return $this->listening;
    }

    public function getMemoryUsage(): int
    {
        return $this->memoryUsage;
    }

    public function getConnections(): int
    {
        return $this->connections;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
    {
        return [
            'pid' => $this->pid,
            'listening' => $this->listening,
            'memoryUsage' => $this->memoryUsage,
            'connections' => $this->connections
        ];
    }
}