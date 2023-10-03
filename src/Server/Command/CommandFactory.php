<?php

namespace Waveman\Server\Command;

use Waveman\Server\Channel\CommandFactoryInterface;
use Waveman\Server\Channel\CommandInterface;
use Waveman\Server\Channel\Message;
use Waveman\Server\Exception\InvalidArgumentException;

final class CommandFactory implements CommandFactoryInterface
{
    private array $commands = [
        CloseCommand::class => 0,
        ErrorCommand::class => 1,
        WorkerCloseCommand::class => 2
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
        return match($class){
            CloseCommand::class => new CloseCommand($message->getPayload()['graceful']),
            ErrorCommand::class => new ErrorCommand($message->getPayload()),
            WorkerCloseCommand::class => new WorkerCloseCommand()
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