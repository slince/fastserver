<?php

namespace Waveman\Cluster;

use React\Socket\SocketServer;

final class Cluster
{
    public const WAVE_MAN_NAME = 'X_WAVE_MAN_ID';

    public bool $isPrimary;

    private int $id = 0;

    private WorkerPool $workers;

    private static ?Cluster $instance = null;

    private function __construct()
    {
        $this->isPrimary = getenv(self::WAVE_MAN_NAME) === false;
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

    public function run(bool $blocking = true): void
    {
        $this->workers->wait($blocking);
    }

    public function listen(string $address, array $context = []): SocketServer
    {
        if ($this->workers instanceof ForkWorkerPool) {
            $context['tcp'] ??= [];
            $context['tcp']['so_reuseport'] = true;
        }
        return new SocketServer($address, $context);
    }
}