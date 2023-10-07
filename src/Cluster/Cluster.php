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