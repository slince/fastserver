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

namespace Waveman\Server\Worker;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Waveman\Server\ServerInterface;

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
     * @var LoopInterface
     */
    protected LoopInterface $loop;

    /**
     * @var ServerInterface
     */
    protected ServerInterface $server;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    public function __construct(string $type, int $capacity, ServerInterface $server, ?LoggerInterface $logger = null, ?LoopInterface $loop = null)
    {
        $this->type = $type;
        $this->capacity = $capacity;
        $this->server = $server;
        $this->logger = $logger ?? new NullLogger();
        $this->loop = $loop ?? Loop::get();
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
    public function remove(Worker $worker): void
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
    public function run(): void
    {
        $this->status = self::STATUS_STARTED;
        foreach ($this->workers as $worker) {
            $worker->start();
        }
    }

    /**
     * Build worker pools.
     */
    public function build(): WorkerPool
    {
        for ($i = 0; $i < $this->capacity; $i++) {
            $this->add($this->createWorker($i));
        }
        return $this;
    }

    /**
     * Close all workers.
     * @param bool $graceful
     */
    protected function closeWorkers(bool $graceful): void
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
            return new ForkWorker($id, $this->server, $this->loop, $this->logger);
        }
        return new ProcWorker($id, $this->server, $this->loop, $this->logger);
    }
}