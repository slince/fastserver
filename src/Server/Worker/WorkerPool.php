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

namespace Waveman\Server\Worker;

use Waveman\Server\Exception\InvalidArgumentException;
use Waveman\Server\Server;

abstract class WorkerPool implements \IteratorAggregate, \Countable
{
    /**
     * The capacity of the pool.
     *
     * @var int
     */
    protected int $capacity;

    /**
     * @var Worker[]
     */
    protected array $workers = [];

    /**
     * @var Server
     */
    protected Server $server;

    public function __construct(int $capacity, Server $server)
    {
        $this->capacity = $capacity;
        $this->server = $server;
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
        $this->run();
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
     * @return void
     */
    public function start(int $id): void
    {
        $worker = $this->create($id);
        $this->add($worker);
        $worker->start();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->workers);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->workers);
    }

    /**
     * Starts the work pool.
     */
    public function run(): void
    {
        for ($i = 0; $i < $this->capacity; $i++) {
            $this->start($i);
        }
    }

    /**
     * Close all workers.
     * @param bool $graceful
     */
    public function close(bool $graceful): void
    {
        foreach ($this->workers as $worker) {
            $this->server->getLogger()->debug(sprintf('Send close command to %d', $worker->getPid()));
            $worker->close($graceful);
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
     * @return Worker|null
     */
    abstract public function wait(bool $blocking = true): ?Worker;

    /**
     * Creates a worker pool.
     *
     * @param int $capacity
     * @param Server $server
     * @return WorkerPool
     */
    public static function createPool(int $capacity, Server $server): WorkerPool
    {
        if (function_exists('pcntl_fork')) {
            return new ForkWorkerPool($capacity, $server);
        }
        if (function_exists('proc_open')) {
            return new ProcWorkerPool($capacity, $server);
        }
        throw new InvalidArgumentException('Cannot create worker pool.');
    }
}