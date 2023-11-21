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

use Viso\Server\Exception\MetaException;

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

    /**
     * Pack the given message.
     * 
     * @param Frame $message
     * @return string
     */
    public static function pack(Frame $message): string
    {
        $flags = $message->getFlags();
        if (($flags & self::PAYLOAD_JSON) === self::PAYLOAD_JSON) {
            $payload = json_encode($message->getPayload());
        } else if (($flags & self::PAYLOAD_RAW) === self::PAYLOAD_RAW) {
            $payload = $message->getPayload();
        } else {
            $payload = '';
        }
        $size = strlen($payload);
        $body = pack('CCJ', $message->getType(), $flags, $size);

        if (($flags & self::PAYLOAD_NONE) !== self::PAYLOAD_NONE) {
            $body .= $payload;
        }
        return $body;
    }

    /**
     * Parse message header.
     *
     * @param string $header
     * @return array
     */
    public static function parseHeader(string $header): array
    {
        $result = unpack('Ctype/Cflags/Jsize', $header);
        if (false === $result) {
            throw new MetaException("invalid message header");
        }
        return $result;
    }

    /**
     * Parse message payload.
     *
     * @param int $flags
     * @param string $body
     * @return array|string
     */
    public static function parsePayload(int $flags, string $body): array|string
    {
        if (($flags & self::PAYLOAD_JSON) === self::PAYLOAD_JSON) {
            return json_decode($body, true);
        }
        if (($flags & self::PAYLOAD_NONE) === self::PAYLOAD_NONE) {
            return "";
        }
        return $body;
    }
}