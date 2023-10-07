<?php

namespace Waveman\Server\Command;

use Waveman\Channel\CommandFactoryInterface;
use Waveman\Channel\CommandInterface;
use Waveman\Channel\Message;
use Waveman\Server\ConnectionDescriptor;
use Waveman\Server\Exception\InvalidArgumentException;
use Waveman\Server\WorkerStatus;

final class CommandFactory implements CommandFactoryInterface
{
    private array $commands = [
        NopCommand::class,
        CloseCommand::class,
        ErrorCommand::class,
        WorkerCloseCommand::class,
        ControlCommand::class,
        HeartbeatCommand::class,
        ReloadCommand::class,
        WorkerPingCommand::class,
        WorkerConnectionsCommand::class,
        WorkerStatusCommand::class
    ];

    /**
     * {@inheritdoc}
     */
    public function createMessage(CommandInterface $command): Message
    {
        $class = get_class($command);
        if (false === ($index = array_search($class, $this->commands))) {
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
        return new Message($index,$payload ? Message::PAYLOAD_JSON : Message::PAYLOAD_NONE, $payload);
    }

    /**
     * {@inheritdoc}
     */
    public function createCommand(Message $message): CommandInterface
    {
        if (isset($this->commands[$message->getType()])) {
            throw new InvalidArgumentException(sprintf('The command type %d is not supported', $message->getType()));
        }
        $class = $this->commands[$message->getType()];
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