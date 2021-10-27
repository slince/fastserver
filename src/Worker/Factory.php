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

final class Factory
{
    const TYPE_FORK = 'fork';
    const TYPE_PROC = 'proc';
    const TYPE_THREAD = 'parallel';

    /**
     * Creates a worker pool.
     *
     * @param int $capacity
     * @return WorkerPool
     */
    public static function create(int $capacity)
    {
        if (function_exists('pcntl_fork')) {
            return new ForkWorkerPool($capacity);
        }
        if (class_exists('\\parallel\\Runtime')) {
            return new ParallelWorkerPool($capacity);
        }
        if (function_exists('proc_open')) {
            return new ProcWorkerPool($capacity);
        }
        // fake worker pool.
        return new FakeWorkerPool(1);
    }
}