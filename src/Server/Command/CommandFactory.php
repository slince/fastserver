<?php

namespace Waveman\Server\Command;

use Waveman\Channel\CommandFactoryInterface;
use Waveman\Channel\CommandInterface;
use Waveman\Channel\Frame;
use Waveman\Server\ConnectionDescriptor;
use Waveman\Server\Exception\InvalidArgumentException;

final class CommandFactory implements CommandFactoryInterface
{
    private array $commands = [
        ReloadCommand::class,
        WorkerConnectionsCommand::class,
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
             WorkerConnectionsCommand::class => ['worker_id' => $command->getWorkerId(), 'connections' => $command->getConnections()],
             default => null
        };
        return new Frame($index,$payload ? Frame::PAYLOAD_JSON : Frame::PAYLOAD_NONE, $payload);
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