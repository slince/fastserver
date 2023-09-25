<?php

declare(strict_types=1);

/*
 * This file is part of the fastserver/fastserver package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Waveman\Server\Worker;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Symfony\Component\Process\Process;
use Waveman\Server\Channel\ChannelInterface;
use Waveman\Server\Channel\Command\CLOSE;
use Waveman\Server\Channel\CommandFactory;
use Waveman\Server\ServerInterface;

class ProcWorker extends Worker
{
    /**
     * @var CommandFactory
     */
    protected CommandFactory $commands;

    /**
     * @var Process
     */
    protected Process $process;

    /**
     * @var ChannelInterface
     */
    protected ChannelInterface $control;

    protected bool $inChildProcess = false;

    public function __construct(int $id, ServerInterface $server, LoopInterface $loop, LoggerInterface $logger)
    {
        parent::__construct($id, $server, $loop, $logger);
        $this->commands = $this->createCommandFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        $config = [
            'address' => $this->server->getOption('address')
        ];
        $entryFile = __DIR__ . '/Internal/worker.php';
        $this->process = Process::fromShellCommandline(sprintf("php %s --config %s", $entryFile, json_encode($config)));
        $this->process->start();
    }
    
    /**
     * Create command factory for the server.
     *
     * @return CommandFactory
     */
    protected function createCommandFactory(): CommandFactory
    {
        return new CommandFactory([CLOSE::class]);
    }
}