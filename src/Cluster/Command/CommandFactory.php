<?php

namespace Viso\Cluster\Command;

use Viso\Channel\CommandFactoryInterface;
use Viso\Channel\CommandInterface;
use Viso\Channel\Exception\InvalidArgumentException;
use Viso\Channel\Frame;
use Viso\Cluster\ConnectionDescriptor;
use Viso\Cluster\WorkerStatus;

final class CommandFactory implements CommandFactoryInterface
{
    private array $commands = [
        NopCommand::class,
        CloseCommand::class,
        WorkerCloseCommand::class,
        ControlCommand::class,
        HeartbeatCommand::class,
        MessageCommand::class,
        ReloadCommand::class,
        WorkerConnectionsCommand::class,
        WorkerPingCommand::class,
        WorkerStatusCommand::class
    ];

    /**
     * {@inheritdoc}
     */
    public function createFrame(CommandInterface $command): Frame
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
             WorkerConnectionsCommand::class => ['worker_id' => $command->getWorkerId(), 'connections' => $command->getConnections()],
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
            WorkerConnectionsCommand::class => new WorkerConnectionsCommand($payload['worker_id'], array_map(fn($item)=> new ConnectionDescriptor(...$item), $payload['connections'])),
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