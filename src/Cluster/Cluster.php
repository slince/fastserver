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

use React\Socket\SocketServer;

final class Cluster
{
    public const WAVE_MAN_NAME = 'X_WAVE_MAN_PID';
    public const WAVE_MAN_WORKER_NAME = 'X_WAVE_MAN_WID';

    public bool $isPrimary;

    public ?Worker $worker = null;

    public WorkerPool $workers;

    private int $id = 0;

    private static ?Cluster $instance = null;

    private function __construct(callable $callback = null)
    {
        $this->isPrimary = getenv(self::WAVE_MAN_NAME) === false;
        $this->workers = WorkerPool::createPool($callback);
        if (!$this->isPrimary) {
            $workerId = getenv(self::WAVE_MAN_WORKER_NAME) ?? 0;
            $this->worker = $this->workers->create($workerId);
            $this->worker->run();
        }
    }

    /**
     * Returns the cluster instance.
     *
     * @return Cluster
     */
    public static function get(): Cluster
    {
        if (null === self::$instance) {
            self::$instance = new Cluster();
        }
        return self::$instance;
    }

    /**
     * Fork a worker.
     *
     * @return Worker
     */
    public function fork(): Worker
    {
        return $this->workers->start($this->id ++);
    }

    /**
     * Run the cluster.
     *
     * @param bool $blocking
     * @return void
     */
    public function run(bool $blocking = true): void
    {
        $this->workers->wait($blocking);
    }

    /**
     * Create a socket server by given address.
     *
     * @param string $address
     * @param array $context
     * @return SocketServer
     */
    public function listen(string $address, array $context = []): SocketServer
    {
        if ($this->workers instanceof ForkWorkerPool) {
            $context['tcp'] ??= [];
            $context['tcp']['so_reuseport'] = true;
        }
        return new SocketServer($address, $context);
    }
}