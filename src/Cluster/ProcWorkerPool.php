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

final class ProcWorkerPool extends WorkerPool
{
    /**
     * {@inheritdoc}
     */
    public function create(int $id): Worker
    {
        return new ProcWorker($id, $this->cluster);
    }

    /**
     * {@inheritdoc}
     */
    public function wait(bool $blocking = true): \Traversable
    {
        do {
            /* @var ProcWorker $worker */
            foreach ($this->workers as $worker) {
                $process = $worker->getProcess();
                if ($process->isTerminated()) {
                    $worker->terminate();
                    yield $worker;
                }
            }
            usleep(2000);
        } while($blocking);
    }
}