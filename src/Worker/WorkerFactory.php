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
use React\EventLoop\LoopInterface;

final class WorkerFactory
{
    const TYPE_FORK = 'fork';
    const TYPE_PROC = 'proc';

    /**
     * Creates a worker pool.
     *
     * @param int $capacity
     * @param ServerInterface $server
     * @param LoggerInterface $logger
     * @param LoopInterface $loop
     * @return WorkerPool
     */
    public static function create(int $capacity, ServerInterface $server, LoggerInterface $logger, LoopInterface $loop)
    {
        if (function_exists('pcntl_fork')) {
            return new ForkWorkerPool($capacity, $server, $logger, $loop);
        }
        if (function_exists('proc_open')) {
            return new ProcWorkerPool($capacity, $server, $logger, $loop);
        }
        // fake worker pool.
        return new FakeWorkerPool(1, $server, $logger, $loop);
    }
}