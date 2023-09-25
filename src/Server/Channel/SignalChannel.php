<?php

namespace Waveman\Server\Channel;

use Slince\Process\Process;
use Waveman\Server\Channel\Command\CommandInterface;
use Waveman\Server\Exception\InvalidArgumentException;

final class SignalChannel implements ChannelInterface
{
    protected Process $process;

    protected array $signalMap;
    protected array $commandMap;

    public function __construct(Process $process, array $signalMap)
    {
        $this->process = $process;
        $this->signalMap = $signalMap;
    }

    public function executeCommand(CommandInterface $command): void
    {
        if (!in_array($command->getCommandId(), $this->signalMap)) {
            throw new InvalidArgumentException(sprintf('The command %s is not supported by signal channel', $command->getCommandId()));
        }
        $this->process->signal($this->signalMap[$command->getCommandId()]);
    }

    public function listen(callable $callback): void
    {

    }
}