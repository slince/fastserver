<?php

namespace Waveman\Server;

final class WorkerStatus
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
}