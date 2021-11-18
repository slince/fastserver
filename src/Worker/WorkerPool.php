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
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

abstract class WorkerPool implements \IteratorAggregate, \Countable
{
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

    public function __construct(int $capacity)
    {
        $this->capacity = $capacity;
    }

    public function configure(LoopInterface $loop, LoggerInterface $logger, ServerInterface $server)
    {
        $this->loop = $loop;
        $this->logger = $logger;
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
        if ($index = array_search($worker, $this->workers) !== false) {
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
            $this->add($this->createWorker($i, $this->loop, $this->server));
        }
    }

    abstract public function createWorker(int $id, LoopInterface $loop, ServerInterface $server);
}