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
namespace Viso\Cluster\Command;

final class MessageCommand implements PayloadCommandInterface
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    /**
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'MESSAGE';
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandKey(): string
    {
        return $this->getCommandId() . $this->message;
    }
}