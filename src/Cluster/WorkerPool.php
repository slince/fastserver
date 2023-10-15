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

use Waveman\Channel\CommandInterface;
use Waveman\Cluster\Exception\InvalidArgumentException;

abstract class WorkerPool implements \IteratorAggregate, \Countable
{
    /**
     * @var Worker[]
     */
    protected array $workers = [];

    protected Cluster $cluster;

    public function __construct(Cluster $cluster)
    {
        $this->cluster = $cluster;
    }

    /**
     * Add a worker to this pool.
     *
     * @param Worker $worker
     */
    public function add(Worker $worker): void
    {
        $this->workers[] = $worker;
    }

    /**
     * Gets the worker by its process id.
     *
     * @param int $pid
     * @return Worker|null
     */
    public function get(int $pid): ?Worker
    {
        foreach ($this->workers as $worker) {
            if ($pid === $worker->getPid()) {
                return $worker;
            }
        }
        return null;
    }

    /**
     * Remove the given worker.
     *
     * @param Worker $worker
     */
    public function remove(Worker $worker): void
    {
        $index = array_search($worker, $this->workers);
        if (false !== $index) {
            unset($this->workers[$index]);
        }
    }

    /**
     * Remove the work by its pid.
     *
     * @param int $pid
     * @return void
     */
    public function removeByPid(int $pid): void
    {
        $worker = $this->get($pid);
        if (null === $worker) {
            return;
        }
        $this->remove($worker);
    }

    protected function ensure(int $pid): Worker
    {
        $worker = $this->get($pid);
        if (null === $worker) {
            throw new InvalidArgumentException(sprintf('Cannot find worker with pid %d', $pid));
        }
        return $worker;
    }

    /**
     * Restart one worker by its pid.
     *
     * @param int $pid
     * @return void
     */
    public function restart(int $pid): void
    {
        $worker = $this->ensure($pid);
        $this->start($worker->getId());
        if ($worker->getStatus() === Worker::STATUS_STARTED) {
            $worker->close(true);
        }
        $this->remove($worker);
    }

    /**
     * Updates the worker updated time by its pid.
     *
     * @param int $pid
     * @return void
     */
    public function heartbeat(int $pid): void
    {
        $worker = $this->ensure($pid);
        $worker->heartbeat();
    }

    /**
     * Restarts worker pools.
     *
     * @return void
     */
    public function restartAll(): void
    {
        $former = $this->workers;
        // Start new workers.
        for ($i = 0; $i < count($former); $i++) {
            $this->start($i);
        }
        // Close old workers.
        foreach ($former as $worker) {
            $worker->close(true);
            $this->remove($worker);
        }
    }

    /**
     * Start a new worker.
     *
     * @param int $id
     * @return Worker
     */
    public function start(int $id): Worker
    {
        $worker = $this->create($id);
        $this->add($worker);
        $worker->start();
        return $worker;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->workers);
    }

    /**
     * Checks whether the worker pool is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->workers);
    }

    /**
     * Close all workers.
     * @param bool $graceful
     */
    public function close(bool $graceful): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->getStatus() === Worker::STATUS_STARTED) {
                $worker->close($graceful);
            }
        }
    }

    /**
     * Send command to all workers.
     * 
     * @param CommandInterface $command
     */
    public function send(CommandInterface $command): void
    {
        foreach ($this->workers as $worker) {
            $worker->send($command);
        }
    }

    /**
     * create a worker instance.
     *
     * @param int $id
     * @return Worker
     */
    abstract public function create(int $id): Worker;

    /**
     * Wait a worker close.
     *
     * @param bool $blocking
     * @return \Traversable<Worker>
     */
    abstract public function wait(bool $blocking = true): \Traversable;

    /**
     * Creates a worker pool.
     * @param Cluster $cluster
     * @param callable|null $callback
     * @return WorkerPool
     */
    public static function createPool(Cluster $cluster, callable $callback = null): WorkerPool
    {
        if (function_exists('pcntl_fork')) {
            return new ForkWorkerPool($cluster, $callback);
        }
        if (function_exists('proc_open')) {
            return new ProcWorkerPool($cluster);
        }
        throw new InvalidArgumentException('Cannot create worker pool.');
    }
}