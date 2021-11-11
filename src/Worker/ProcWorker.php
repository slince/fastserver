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

use React\EventLoop\LoopInterface;
use React\Stream\CompositeStream;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use FastServer\Bridge\Command\CommandFactory;
use FastServer\Bridge\BridgeInterface;
use FastServer\Bridge\StreamBridge;
use FastServer\Process\Process;
use FastServer\Process\ProcProcess;
use FastServer\ServerInterface;

class ProcWorker extends Worker
{
    /**
     * @var CommandFactory
     */
    protected $commands;

    /**
     * @var ProcProcess
     */
    protected $process;

    /**
     * @var BridgeInterface
     */
    protected $control;

    /**
     * @var bool
     */
    protected $isSupportSignal = false;

    protected $inChildProcess = false;

    public function __construct(LoopInterface $loop, ServerInterface $server)
    {
        parent::__construct($loop, $server);
        $this->commands = $this->createCommandFactory();
        $this->isSupportSignal = Process::isSupportPosixSignal();
    }

    public function start()
    {
        $config = [
            'address' => $this->server->getOption('address')
        ];
        $entryFile = __DIR__ . '/Internal/worker.php';
        $this->process = new ProcProcess(sprintf("php %s --configuration %s", $entryFile, json_encode($config)));
        $this->process->start(false);
        $this->control = new StreamBridge(new CompositeStream(
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