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

use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

final class ProcWorkerPool extends WorkerPool
{

    private int $listenPort = 6001;

    private array $context;


    private function createChannelServer(): void
    {
        $address = sprintf('tcp://127.0.0.1:%d', $this->listenPort);
        $server = new SocketServer($address, ['tcp' => $address], $this->cluster->loop);

        $server->on('connection', function (ConnectionInterface $connection){


        });
    }


    /**
     * {@inheritdoc}
     */
    public function create(int $id): Worker
    {
        return new ProcWorker($id, $this->cluster, $this->logger);
    }
}