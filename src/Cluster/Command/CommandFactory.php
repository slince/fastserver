<?php

namespace Viso\Cluster\Command;

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
        PongCommand::class,
        MessageCommand::class,
        ReloadCommand::class,
        ConnectionsCommand::class,
        PingCommand::class,
        StatusCommand::class
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
             PingCommand::class => (string)$command->getWorkerId(),
             StatusCommand::class => ['worker_id' => $command->getWorkerId(), 'status' => $command->getStatus()],
             ConnectionsCommand::class => ['worker_id' => $command->getWorkerId(), 'connections' => $command->getConnections()],
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
        if (!isset($this->commands[$frame->getType()])) {
            throw new InvalidArgumentException(sprintf('The command type %d is not supported', $frame->getType()));
        }
        $class = $this->commands[$frame->getType()];
        $payload = $frame->getPayload();
        return match($class){
            CloseCommand::class => new CloseCommand($frame->getPayload()['graceful']),
            ControlCommand::class => new ControlCommand(intval($frame->getPayload())),
            MessageCommand::class => new MessageCommand($frame->getPayload()),
            PingCommand::class => new PingCommand(intval($frame->getPayload())),
            StatusCommand::class => new StatusCommand($payload['worker_id'], new WorkerStatus(...$payload['status'])),
            ConnectionsCommand::class => new ConnectionsCommand($payload['worker_id'], array_map(fn($item)=> new ConnectionDescriptor(...$item), $payload['connections'])),
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