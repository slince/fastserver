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

class CLOSE implements CommandInterface
{
    /**
     * Close gracefully
     * @var bool
     */
    protected $graceful = false;

    public function __construct(bool $graceful)
    {
        $this->graceful = $graceful;
    }

    /**
     * @return bool
     */
    public function isGraceful(): bool
    {
        return $this->graceful;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'CLOSE';
    }

    /**
     * {@inheritdoc}
     */
    public function createMessage(): Message
    {
        return new Message(Message::PAYLOAD_CONTROL, ['graceful' => $this->graceful]);
    }

    /**
     * {@inheritdoc}
     */
    public static function fromMessage(Message $message): CommandInterface
    {
        return new static($message->getArgument('graceful'));
    }
}