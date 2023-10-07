<?php

namespace Waveman\Server\Command;

use Waveman\Channel\CommandInterface;

final class ControlCommand implements CommandInterface
{
    public const CONNECTIONS = 0;
    public const STATUS = 2;

    private int $flags;

    public function __construct(int $flags)
    {
        $this->flags = $flags;
    }

    /**
     * Returns control flags.
     *
     * @return int
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    public function getCommandId(): string
    {
        return 'CONTROL';
    }

    public function getCommandKey(): string
    {
        return $this->getCommandId();
    }
}