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
use React\Socket\Connector;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Process as SymfonyProcess;
use Viso\Channel\ChannelInterface;
use Viso\Channel\StreamChannel;
use Viso\Cluster\Cluster;
use Viso\Cluster\Command\RegisterCommand;
use Viso\Cluster\Exception\RuntimeException;

final class ProcWorker extends Worker
{
    private int $listenPort;

    /**
     * @var SymfonyProcess
     */
    private SymfonyProcess $process;

    public function __construct(int $id, Cluster $cluster, LoggerInterface $logger, callable $callback, int $listenPort)
    {
        parent::__construct($id, $cluster, $logger, $callback);
        $this->listenPort = $listenPort;
    }

    /**
     * {@inheritdoc}
     */
    public function getPid(): int
    {
        $this->cluster->requireInMainProcess(__METHOD__);
        return $this->process->getPid();
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        $this->cluster->requireInMainProcess(__METHOD__);
        return $this->process->isRunning();
    }

    /**
     * Set channel instance for the worker.
     * @internal
     * @param ChannelInterface $channel
     * @return void
     */
    public function attachChannel(ChannelInterface $channel): void
    {
        $this->control = $channel;
    }

    /**
     * {@inheritdoc}
     */
    protected function doRun(callable $fulfilled): void
    {
        $address = sprintf('127.0.0.1:%d', $this->listenPort);
        $connector = new Connector();
        $connector->connect($address)
            ->then(function(ConnectionInterface $connection) use($fulfilled) {
                $connection->on('error', function(){
                    $this->logger->debug('The channel is disconnect');
                    $this->stop();
                });
                $this->control = new StreamChannel($connection);
                $this->sendCommand(new RegisterCommand($this->getPid()));
                call_user_func($fulfilled);
            }, function(\Exception $exception) use($address){
                throw new RuntimeException(sprintf('Cannot connect to channel server %s, error: %s', $address, $exception->getMessage()), $exception->getCode(), $exception);
            });
    }

    /**
     * {@inheritdoc}
     */
    public function doStart(): void
    {
        $entry = self::getEntryFile();
        $phpExecutableFinder = new PhpExecutableFinder();
        $phpExecutablePath = $phpExecutableFinder->find();
        $env = [
            Cluster::VISO_PID => getmypid(),
            Cluster::VISO_WORKER_ID => $this->getId()
        ];
        $this->process = new Process([$phpExecutablePath, $entry], null, $env, null, 0);
        $this->process->start();
    }

    /**
     * {@inheritdoc}
     */
    protected function doClose(): void
    {
        $this->process->stop(10,\SIGKILL);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSignal(int $signal): void
    {
        $this->process->signal($signal);
    }

    private static function getEntryFile(): string
    {
        $filename = $_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'];
        if (empty($filename)) {
            throw new RuntimeException('Cannot find entry file.');
        }
        return $filename;
    }
}