<?php

declare(strict_types=1);

/*
 * This file is part of the fastserver/fastserver package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FastServer\Worker;

use FastServer\ServerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

abstract class WorkerPool implements \IteratorAggregate, \Countable
{
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
    protected $status = self::STATUS_READY;

    /**
     * The capacity of the pool.
     *
     * @var int
     */
    protected $capacity;

    /**
     * @var Worker[]
     */
    protected $workers = [];

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var ServerInterface
     */
    protected $server;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(int $capacity, ServerInterface $server, ?LoggerInterface $logger = null)
    {
        $this->capacity = $capacity;
        $this->logger = $logger ?? new NullLogger();
        $this->server = $server;
    }

    /**
     * Add a worker to this pool.
     *
     * @param Worker $worker
     */
    public function add(Worker $worker)
    {
        $this->workers[] = $worker;
    }

    /**
     * Gets the worker by its process id.
     *
     * @param int $pid
     * @return Worker|null
     */
    public function getWorker(int $pid): ?Worker
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
    public function remove(Worker $worker)
    {
        $index = array_search($worker, $this->workers);
        if (false !== $index) {
            unset($this->workers[$index]);
        }
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
    public function run()
    {
        $this->status = self::STATUS_STARTED;
        foreach ($this->workers as $worker) {
            $worker->start();
        }
    }

    /**
     * Build worker pools.
     */
    public function build()
    {
        for ($i = 0; $i < $this->capacity; $i++) {
            $this->add($this->createWorker($i));
        }
    }

    /**
     * Close all workers.
     * @param bool $graceful
     */
    protected function closeWorkers(bool $graceful)
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
    abstract public function createWorker(int $id): Worker;

    /**
     * Wait
     */
    abstract public function wait();
}