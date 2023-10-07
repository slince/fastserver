<?php

namespace Waveman\Server\Command;

use Waveman\Channel\PayloadCommandInterface;

final class ControlCommand implements PayloadCommandInterface
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

    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'CONTROL';
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandKey(): string
    {
        return $this->getCommandId() . $this->flags;
    }
}