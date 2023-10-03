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

namespace Waveman\Server\Worker;

use Symfony\Component\Process\Process;
use Waveman\Server\Channel\ChannelInterface;
use Waveman\Server\Command\CloseCommand;
use Waveman\Server\Command\CommandFactory;
use Waveman\Server\ServerInterface;

class ProcWorker extends Worker
{
    /**
     * @var CommandFactory
     */
    private CommandFactory $commands;

    /**
     * @var Process
     */
    private Process $process;

    /**
     * @var ChannelInterface
     */
    private ChannelInterface $control;

    private bool $inChildProcess = false;

    public function __construct(int $id, ServerInterface $server)
    {
        parent::__construct($id, $server);
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
    private function createCommandFactory(): CommandFactory
    {
        return new CommandFactory([CloseCommand::class]);
    }

    /**
     * {@inheritdoc}
     */
    public function close(bool $graceful = false): void
    {
        $this->process->stop();
    }
}