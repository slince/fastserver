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
    private int $id;
    private int $pid;
    private string $listening;
    private int $memoryUsage;
    private int $connections;
    private int $aliveSeconds;

    /**
     * @param int $id
     * @param int $pid
     * @param string $listening
     * @param int $memoryUsage
     * @param int $connections
     * @param int $aliveSeconds
     */
    public function __construct(int $id, int $pid, string $listening, int $memoryUsage, int $connections, int $aliveSeconds)
    {
        $this->id = $id;
        $this->pid = $pid;
        $this->listening = $listening;
        $this->memoryUsage = $memoryUsage;
        $this->connections = $connections;
        $this->aliveSeconds = $aliveSeconds;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
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
     * @return int
     */
    public function getAliveSeconds(): int
    {
        return $this->aliveSeconds;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'pid' => $this->pid,
            'listening' => $this->listening,
            'memoryUsage' => $this->memoryUsage,
            'connections' => $this->connections,
            'aliveSeconds' => $this->aliveSeconds
        ];
    }
}