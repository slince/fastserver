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

namespace Viso\Channel;

final class Frame
{
    public const HEADER_SIZE = 10;
    public const BUFFER_SIZE = 65536;

    /** Payload flags.*/
    public const PAYLOAD_NONE = 1;
    public const PAYLOAD_RAW = 2;
    public const PAYLOAD_JSON = 4;

    private int $type;
    
    /**
     * @var int
     */
    private int $flags;

    /**
     * @var mixed
     */
    private mixed $payload;

    public function __construct(int $type, int $flags, mixed $payload)
    {
        $this->type = $type;
        $this->flags = $flags;
        $this->payload = $payload;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * @return string
     */
    public function getPayload(): mixed
    {
        return $this->payload;
    }
}