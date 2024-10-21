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
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use Viso\Channel\Frame;
use Viso\Channel\StreamChannel;
use Viso\Cluster\Cluster;
use Viso\Cluster\Command\CommandFactory;

final class ProcWorkerPool extends WorkerPool
{
    public const DEFAULT_LISTEN_PORT = 6001;

    private int $listenPort;

    public function __construct(Cluster $cluster, LoggerInterface $logger, callable $callback, int $listenPort)
    {
        parent::__construct($cluster, $logger, $callback);
        $this->listenPort = $listenPort;
    }

    /**
     * Create channel server.
     *
     * @return void
     */
    private function createChannelServer(): void
    {
        $address = sprintf('tcp://127.0.0.1:%d', $this->listenPort);
        $server = new SocketServer($address, [], $this->cluster->loop);
        $this->logger->debug('Start channel server for the cluster');

        $server->on('connection', function (ConnectionInterface $connection){
            $channel = new StreamChannel($connection);
            $commandFactory = CommandFactory::create();
            $channel->listen(function(Frame $frame) use($commandFactory, $channel, $connection){
                $command = $commandFactory->createCommand($frame);
                if ('REGISTER' === $command->getCommandId()) {
                    /* @var ProcWorker $worker */
                    $worker = $this->get($command->getWorkerId());
                    if (null === $worker) {
                        $this->logger->warning(sprintf("Cannot find worker %d", $command->getWorkerId()));
                    }
                    $worker->setChannel($channel);
                } else {
                    $this->logger->warning('Unrecognized command');
                    $connection->close();
                }
            }, true);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function run(): void
    {
        $this->createChannelServer();
        parent::run();
    }

    /**
     * {@inheritdoc}
     */
    public function create(int $id): Worker
    {
        return new ProcWorker($id, $this->cluster, $this->logger, $this->callback, $this->listenPort);
    }
}