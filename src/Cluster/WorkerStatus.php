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

namespace Viso\Cluster;

use Viso\Cluster\Worker\Worker;

final class WorkerStatus implements \JsonSerializable
{
    private int $id;
    private int $pid;
    private int $memoryUsage;
    private int $aliveSeconds;

    /**
     * @param int $id
     * @param int $pid
     * @param int $memoryUsage
     * @param int $aliveSeconds
     */
    public function __construct(int $id, int $pid, int $memoryUsage, int $aliveSeconds)
    {
        $this->id = $id;
        $this->pid = $pid;
        $this->memoryUsage = $memoryUsage;
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

    public function getMemoryUsage(): int
    {
        return $this->memoryUsage;
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
            'memoryUsage' => $this->memoryUsage,
            'aliveSeconds' => $this->aliveSeconds
        ];
    }

    /**
     * Create a worker status.
     *
     * @param Worker $worker
     * @return WorkerStatus
     */
    public static function create(Worker $worker): WorkerStatus
    {
        return new WorkerStatus(
            $worker->getId(),
            getmypid(),
            memory_get_usage(true),
            $worker->getAliveSeconds()
        );
    }
}