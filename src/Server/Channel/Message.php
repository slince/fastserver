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

namespace Waveman\Server\Channel;

use Waveman\Server\Exception\MetaException;

final class Message
{
    public const HEADER_SIZE = 17;
    public const BUFFER_SIZE = 65536;

    /** Payload flags.*/
    public const PAYLOAD_NONE    = 2;
    public const PAYLOAD_RAW     = 4;
    public const PAYLOAD_ERROR   = 8;
    public const PAYLOAD_JSON = 16;

    /**
     * @var int
     */
    protected int $flags;

    /**
     * @var string
     */
    protected string $payload = '';

    public function __construct(int $flags, string $payload = '')
    {
        $this->flags = $flags;
        $this->payload = $payload;
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
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * Pack the given message.
     * 
     * @param Message $message
     * @return string
     */
    public static function pack(Message $message): string
    {
        $flags = $message->getFlags();
        $payload = '';
        if ($message->getPayload()) {
            $payload = json_encode($message->getPayload());
        }
        $size = strlen($payload);
        $body = pack('CPJ', $flags, $size, $size);

        if (!($flags & Message::PAYLOAD_NONE)) {
            $body .= $payload;
        }
        return $body;
    }

    /**
     * Parse message header.
     *
     * @param string $header
     * @return array|false
     */
    public static function parseHeader(string $header): false|array
    {
        $result = unpack("Cflags/Psize/Jrevs", $header);
        if (!is_array($result)) {
            throw new MetaException("invalid meta");
        }
        if ($result['size'] != $result['revs']) {
            throw new MetaException("invalid meta (checksum)");
        }
        return $result;
    }
}