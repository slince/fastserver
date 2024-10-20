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

final class ControlCommand implements CommandInterface
{
    public const CONNECTIONS = 1;
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
}