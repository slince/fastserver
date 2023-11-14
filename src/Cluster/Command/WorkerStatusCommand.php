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

namespace Viso\Cluster\Command;

use Viso\Channel\PayloadCommandInterface;
use Viso\Cluster\WorkerStatus;

final class WorkerStatusCommand extends WorkerCommand implements PayloadCommandInterface
{
    private WorkerStatus $workerStatus;

    public function __construct(int $workerId, WorkerStatus $workerStatus)
    {
        parent::__construct($workerId);
        $this->workerStatus = $workerStatus;
    }

    /**
     * @return WorkerStatus
     */
    public function getWorkerStatus(): WorkerStatus
    {
        return $this->workerStatus;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'WORKER_STATUS';
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandKey(): string
    {
        return $this->getCommandId() . json_encode($this->workerStatus);
    }
}