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

final class WorkerPool implements \IteratorAggregate, \Countable
{
    const WORKER_PROC = 'proc';
    const WORKER_FORK = 'fork';

    /**
     * process status,running
     * @var string
     */
    const STATUS_READY = 'ready';

    /**
     * process status,running
     * @var string
     */
    const STATUS_STARTED = 'started';

    /**
     * closing.
     */
    const STATUS_CLOSING = 'closing';

    /**
     * process status,terminated
     * @var string
     */
    const STATUS_TERMINATED = 'terminated';

    /**
     * @var string
     */
    protected string $status = self::STATUS_READY;

    protected string $type;

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

    public function __construct(string $type, int $capacity, Server $server)
    {
        $this->type = $type;
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

    /**
     * Restart one worker by its pid.
     *
     * @param int $pid
     * @return void
     */
    public function restart(int $pid): void
    {
        $worker = $this->get($pid);
        if (null === $worker) {
            throw new InvalidArgumentException(sprintf('Cannot find worker with pid %d', $pid));
        }
        $this->start($worker->getId());
        $worker->close(true);
        $this->remove($worker);
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
        $worker = $this->createWorker($id);
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
        $this->status = self::STATUS_STARTED;
    }

    /**
     * Close all workers.
     * @param bool $graceful
     */
    public function close(bool $graceful): void
    {
        foreach ($this->workers as $worker) {
            $worker->close($graceful);
        }
    }

    /**
     * create a worker instance.
     *
     * @param int $id
     * @return Worker
     */
    public function createWorker(int $id): Worker
    {
        if ($this->type === self::WORKER_FORK) {
            return new ForkWorker($id, $this->server);
        }
        return new ProcWorker($id, $this->server);
    }

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
            $type = self::WORKER_FORK;
        } elseif (function_exists('proc_open')) {
            $type = self::WORKER_PROC;
        } else {
            throw new InvalidArgumentException('Cannot create worker pool.');
        }
        return new WorkerPool($type, $capacity, $server);
    }
}