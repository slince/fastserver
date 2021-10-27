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

    public function __construct(int $capacity)
    {
        $this->capacity = $capacity;
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
     *
     * @param ServerInterface $server
     * @param LoopInterface $loop
     * @return $this
     */
    public function resolve(ServerInterface $server, LoopInterface $loop)
    {
        for ($i = 0; $i < $this->capacity, $i++;) {
            $this->add($this->createWorker($loop, $server));
        }
        return $this;
    }

    abstract public function createWorker(LoopInterface $loop, ServerInterface $server);
}