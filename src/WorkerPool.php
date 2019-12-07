<?php

namespace FastServer;

use FastServer\Worker\WorkerInterface;

class WorkerPool
{
    /**
     * @var WorkerInterface[]
     */
    protected $workers = [];

    public function add(WorkerInterface $worker)
    {
        $this->workers[] = $worker;
    }

    public function remove(WorkerInterface $worker)
    {
        if ($index = array_search($worker, $this->workers) !== false) {
            unset($this->workers[$index]);
        }
    }

    public function count()
    {
        return count($this->workers);
    }
}