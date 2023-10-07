<?php

declare(strict_types=1);

/*
 * This file is part of the waveman/waveman package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Waveman\Server\Command;

use Waveman\Channel\CommandInterface;
use Waveman\Channel\Frame;

final class CloseCommand implements CommandInterface
{
    /**
     * Close gracefully
     * @var bool
     */
    protected bool $graceful = false;

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
    public function getCommandKey(): string
    {
        return $this->getCommandId() . '_' . $this->isGraceful();
    }

    /**
     * {@inheritdoc}
     */
    public function createMessage(): Frame
    {
        return new Frame(Frame::PAYLOAD_CONTROL, ['graceful' => $this->graceful]);
    }

    /**
     * {@inheritdoc}
     */
    public static function fromMessage(Frame $message): CommandInterface
    {
        return new static($message->getArgument('graceful'));
    }
}