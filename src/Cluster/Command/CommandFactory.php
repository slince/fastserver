<?php

namespace Waveman\Cluster\Command;

use Waveman\Channel\CommandFactoryInterface;
use Waveman\Channel\CommandInterface;
use Waveman\Channel\Frame;
use Waveman\Cluster\Exception\InvalidArgumentException;
use Waveman\Cluster\WorkerStatus;

final class CommandFactory implements CommandFactoryInterface
{
    private array $commands = [
        NopCommand::class,
        CloseCommand::class,
        WorkerCloseCommand::class,
        ControlCommand::class,
        HeartbeatCommand::class,
        MessageCommand::class,
        WorkerPingCommand::class,
        WorkerStatusCommand::class
    ];

    /**
     * {@inheritdoc}
     */
    public function createMessage(CommandInterface $command): Frame
    {
        $class = get_class($command);
        if (false === ($index = array_search($class, $this->commands))) {
            throw new InvalidArgumentException(sprintf('The command %s is not supported', $class));
        }
        $payload = match ($class) {
             CloseCommand::class => ['graceful' => $command->isGraceful()],
             ControlCommand::class => (string)$command->getFlags(),
             MessageCommand::class => $command->getMessage(),
             WorkerPingCommand::class => (string)$command->getWorkerId(),
             WorkerStatusCommand::class => ['worker_id' => $command->getWorkerId(), 'status' => $command->getWorkerStatus()],
             default => null
        };
        $flags = $payload ? (is_string($payload) ? Frame::PAYLOAD_RAW: Frame::PAYLOAD_JSON) : Frame::PAYLOAD_NONE;
        return new Frame($index,$flags, $payload);
    }

    /**
     * {@inheritdoc}
     */
    public function createCommand(Frame $frame): CommandInterface
    {
        if (isset($this->commands[$frame->getType()])) {
            throw new InvalidArgumentException(sprintf('The command type %d is not supported', $frame->getType()));
        }
        $class = $this->commands[$frame->getType()];
        $payload = $frame->getPayload();
        return match($class){
            CloseCommand::class => new CloseCommand($frame->getPayload()['graceful']),
            ControlCommand::class => new ControlCommand(intval($frame->getPayload())),
            MessageCommand::class => new MessageCommand($frame->getPayload()),
            WorkerPingCommand::class => new WorkerPingCommand(intval($frame->getPayload())),
            WorkerStatusCommand::class => new WorkerStatusCommand($payload['worker_id'], new WorkerStatus(...$payload['status'])),
            default => new $class()
        };
    }

    /**
     * {@inheritdoc}
     */
    public function supportCommand(CommandInterface $command): bool
    {
        return in_array(get_class($command), $this->commands);
    }

    /**
     * Create one new command factory.
     * 
     * @return CommandFactory
     */
    public static function create(): CommandFactory
    {
        return new CommandFactory();
    }
}