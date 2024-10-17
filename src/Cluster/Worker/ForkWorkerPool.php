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
namespace Viso\Cluster\Worker;

use Psr\Log\LoggerInterface;
use Viso\Cluster\Cluster;

final class ForkWorkerPool extends WorkerPool
{
    private $callback;

    public function __construct(Cluster $cluster, LoggerInterface $logger, callable $callback)
    {
        parent::__construct($cluster, $logger);
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function create(int $id): Worker
    {
        return new ForkWorker($id, $this->cluster, $this->logger, $this->callback);
    }
}