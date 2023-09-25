<?php

namespace Waveman\Server\Channel\Command;

use Waveman\Server\Channel\Message;

final class WorkerClose implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'WORKER_CLOSE';
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandKey(): string
    {
        return $this->getCommandId();
    }

    /**
     * {@inheritdoc}
     */
    public function createMessage(): Message
    {
        return new Message(Message::PAYLOAD_CONTROL, ['id' => $this->getCommandId()]);
    }

    /**
     * {@inheritdoc}
     */
    public static function fromMessage(Message $message): CommandInterface
    {
        return new WorkerClose();
    }
}