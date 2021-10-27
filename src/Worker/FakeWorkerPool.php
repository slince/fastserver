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

class FakeWorkerPool extends WorkerPool
{
    /**
     * {@inheritdoc}
     */
    public function createWorker(LoopInterface $loop, ServerInterface $server)
    {
        return new Worker($loop, $server);
    }
}