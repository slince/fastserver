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

use Waveman\Server\Channel\CommandInterface;

final class ErrorCommand implements CommandInterface
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
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}