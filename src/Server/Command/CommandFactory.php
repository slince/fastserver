<?php

namespace Waveman\Server\Command;

use Waveman\Server\Channel\CommandFactoryInterface;
use Waveman\Server\Channel\CommandInterface;
use Waveman\Server\Channel\Message;
use Waveman\Server\ConnectionDescriptor;
use Waveman\Server\Exception\InvalidArgumentException;
use Waveman\Server\WorkerStatus;

final class CommandFactory implements CommandFactoryInterface
{
    private array $commands = [
        CloseCommand::class => 0,
        ErrorCommand::class => 1,
        WorkerCloseCommand::class => 2,
        ControlCommand::class => 3,
        HeartbeatCommand::class => 4,
        ReloadCommand::class => 5,
        WorkerPingCommand::class => 6,
        WorkerConnectionsCommand::class => 7,
        WorkerStatusCommand::class => 8
    ];

    /**
     * {@inheritdoc}
     */
    public function createMessage(CommandInterface $command): Message
    {
        $class = get_class($command);
        if (!isset($this->commands[$class])) {
            throw new InvalidArgumentException(sprintf('The command %s is not supported', $class));
        }
        $payload = match ($class) {
             CloseCommand::class => ['graceful' => $command->isGraceful()],
             ErrorCommand::class => ['message' => $command->getMessage()],
             ControlCommand::class => ['flags' => $command->getFlags()],
             WorkerPingCommand::class => ['worker_id' => $command->getWorkerId()],
             WorkerConnectionsCommand::class => ['worker_id' => $command->getWorkerId(), 'connections' => $command->getConnections()],
             WorkerStatusCommand::class => ['worker_id' => $command->getWorkerId(), 'status' => $command->getWorkerStatus()],
             default => null
        };
        return new Message($this->commands[$class],$payload ? Message::PAYLOAD_JSON : Message::PAYLOAD_NONE, $payload);
    }

    /**
     * {@inheritdoc}
     */
    public function createCommand(Message $message): CommandInterface
    {
        $class = array_search($message->getType(), $this->commands);
        if (false === $class) {
            throw new InvalidArgumentException(sprintf('The command type %d is not supported', $message->getType()));
        }
        $payload = $message->getPayload();
        return match($class){
            CloseCommand::class => new CloseCommand($message->getPayload()['graceful']),
            ErrorCommand::class => new ErrorCommand($message->getPayload()),
            WorkerCloseCommand::class => new WorkerCloseCommand(),
            WorkerConnectionsCommand::class => new WorkerConnectionsCommand($payload['worker_id'], array_map(fn($item)=> new ConnectionDescriptor(...$item), $payload['connections'])),
            WorkerStatusCommand::class => new WorkerStatusCommand($payload['worker_id'], new WorkerStatus(...$payload['status'])),
            default => new $class()
        };
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