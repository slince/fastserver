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
namespace Viso\Cluster;

final class ForkWorkerPool extends WorkerPool
{
    private $callback;

    public function __construct(Cluster $cluster, callable $callback)
    {
        parent::__construct($cluster);
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function create(int $id): Worker
    {
        return new ForkWorker($id, $this->cluster, $this->callback);
    }
}