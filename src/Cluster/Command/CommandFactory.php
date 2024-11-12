<?php

namespace Viso\Cluster\Command;

use Viso\Channel\Frame;
use Viso\Cluster\Exception\InvalidArgumentException;
use Viso\Cluster\WorkerStatus;

final class CommandFactory implements CommandFactoryInterface
{
    private array $commands = [
        CloseCommand::class,
        ControlCommand::class,
        PongCommand::class,
        MessageCommand::class,
        PingCommand::class,
        StatusCommand::class,
        RegisterCommand::class,
    ];

    public function __construct(array $commands = [])
    {
        $this->commands = $this->commands + $commands;
    }

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
             MessageCommand::class => ['message' => $command->getMessage(), 'context' => $command->getContext()],
             PingCommand::class, RegisterCommand::class => (string)$command->getWorkerId(),
             StatusCommand::class => ['worker_id' => $command->getWorkerId(), 'status' => $command->getStatus()],
            default => null
        };
        if (null === $payload && $command instanceof PayloadCommandInterface) {
            $payload = $command->getPayload();
        }
        $flags = $payload ? (is_string($payload) ? Frame::PAYLOAD_RAW: Frame::PAYLOAD_JSON) : Frame::PAYLOAD_NONE;
        return new Frame($index, $flags, $payload);
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
        if (is_subclass_of($class, PayloadCommandInterface::class)) {
            return $class::create($payload);
        }
        return match($class){
            CloseCommand::class => new CloseCommand($payload['graceful']),
            ControlCommand::class => new ControlCommand(intval($payload)),
            MessageCommand::class => new MessageCommand($payload['message'], $payload['context']),
            PingCommand::class => new PingCommand(intval($payload)),
            StatusCommand::class => new StatusCommand($payload['worker_id'], new WorkerStatus(...$payload['status'])),
            RegisterCommand::class => new RegisterCommand(intval($payload)),
            default => new $class($payload)
        };
    }

    /**
     * Create one new command factory.
     *
     * @param array $commands
     * @return CommandFactory
     */
    public static function create(array $commands = []): CommandFactory
    {
        return new CommandFactory($commands);
    }
}