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

final class MessageCommand implements CommandInterface
{
    private string $message;

    private array $context;

    public function __construct(string $message, array $context = [])
    {
        $this->message = $message;
        $this->context = $context;
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
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'MESSAGE';
    }
}