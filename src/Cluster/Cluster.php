<?php

namespace Waveman\Cluster;

final class Cluster
{
    public bool $isPrimary = false;

    private int $id = 0;

    private WorkerPool $workers;

    private static ?Cluster $instance = null;

    private function __construct()
    {
        $this->workers = WorkerPool::createPool();
    }

    public static function get(): Cluster
    {
        if (null === self::$instance) {
            self::$instance = new Cluster();
        }
        return self::$instance;
    }

    public function fork(): Worker
    {
        return $this->workers->start($this->id ++);
    }
}