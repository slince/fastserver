<?php

namespace Waveman\Server\Channel;

use React\EventLoop\LoopInterface;
use Slince\Process\Process;
use Waveman\Server\Channel\Command\CommandInterface;
use Waveman\Server\Exception\InvalidArgumentException;

final class SignalChannel implements ChannelInterface
{
    protected ?Process $process;
    protected LoopInterface $loop;

    protected array $signalMap;
    protected array $commandMap;

    public function __construct(?Process $process, LoopInterface $loop, array $signalMap)
    {
        $this->process = $process;
        $this->loop = $loop;
        $this->setSignalMap($signalMap);
    }

    /**
     * Sets signal map.
     * @param array $signalMap
     * @return void
     */
    public function setSignalMap(array $signalMap): void
    {
        $this->signalMap = $signalMap;
        foreach ($this->signalMap as $signal => $command) {
            $this->commandMap[$command->getCommandKey()] = $signal;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function executeCommand(CommandInterface $command): void
    {
        if (!isset($this->commandMap[$command->getCommandKey()])) {
            throw new InvalidArgumentException(sprintf('The command %s is not supported by signal channel', $command->getCommandKey()));
        }
        $this->process->signal($this->commandMap[$command->getCommandKey()]);
    }

    /**
     * {@inheritdoc}
     */
    public function listen(callable $callback): void
    {
        $signalHandler = function(int $signal) use($callback){
            if (!isset($this->signalMap[$signal])) {
                throw new InvalidArgumentException(sprintf('The signal %s is not supported by signal channel', $signal));
            }
            $command = $this->signalMap[$signal];
            $callback($command);
        };
        foreach ($this->signalMap as $signal => $_) {
            $this->loop->addSignal($signal, $signalHandler);
        }
    }
}