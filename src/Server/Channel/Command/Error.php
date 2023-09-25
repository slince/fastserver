<?php

declare(strict_types=1);

/*
 * This file is part of the fastserver/fastserver package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Waveman\Server\Channel\Command;

use Waveman\Server\Channel\Message;

final class Error implements CommandInterface
{
    /**
     * @var string
     */
    protected string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'ERROR';
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
        return new Message(Message::PAYLOAD_ERROR, ['message' => $this->message]);
    }

    /**
     * {@inheritdoc}
     */
    public static function fromMessage(Message $message): CommandInterface
    {
        return new Error($message->getArgument('message'));
    }
}