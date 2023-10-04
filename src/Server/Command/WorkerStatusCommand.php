<?php

namespace Waveman\Server\Command;

use Waveman\Server\WorkerStatus;

final class WorkerStatusCommand extends WorkerCommand
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
    
    public function getCommandId(): string
    {
        return 'WORKER_STATUS';
    }
}