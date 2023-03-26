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

namespace FastServer\Worker;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Stream\CompositeStream;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use FastServer\Communicator\Command;
use FastServer\Communicator\Command\CommandFactory;
use FastServer\Communicator\CommunicatorInterface;
use FastServer\Communicator\StreamCommunicator;
use FastServer\Process\Process;
use FastServer\Process\ProcProcess;
use FastServer\ServerInterface;

class ProcWorker extends Worker
{
    /**
     * @var CommandFactory
     */
    protected CommandFactory $commands;

    /**
     * @var ProcProcess
     */
    protected ProcProcess $process;

    /**
     * @var CommunicatorInterface
     */
    protected CommunicatorInterface $control;

    /**
     * @var bool
     */
    protected bool $isSupportSignal = false;

    protected bool $inChildProcess = false;

    public function __construct(int $id, ServerInterface $server, LoggerInterface $logger, LoopInterface $loop)
    {
        parent::__construct($id, $server, $logger, $loop);
        $this->commands = $this->createCommandFactory();
        $this->isSupportSignal = Process::isSupportPosixSignal();
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
        $this->process = new ProcProcess(sprintf("php %s --config %s", $entryFile, json_encode($config)));
        $this->process->start(false);
        $this->control = new StreamCommunicator(new CompositeStream(
            new ReadableResourceStream($this->process->stdout, $this->loop),
            new WritableResourceStream($this->process->stdin, $this->loop),
        ));
    }

    /**
     * Create command factory for the server.
     *
     * @return CommandFactory
     */
    protected function createCommandFactory(): CommandFactory
    {
        return new CommandFactory([
            'CLOSE' => Command\CLOSE::class,
        ]);
    }
}