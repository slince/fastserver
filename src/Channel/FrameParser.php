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

use Evenement\EventEmitter;
use Viso\Channel\Exception\MetaException;

final class FrameParser extends EventEmitter
{
    /**
     * @var string
     */
    protected string $buffer = '';

    /**
     * Buffer length.
     * @var int
     */
    protected int $length = 0;

    /**
     * @var array|null
     */
    protected ?array $meta = null;

    /**
     * Push incoming data to the parser.
     *
     * @param string $chunk
     */
    public function push(string $chunk): void
    {
        $this->buffer .= $chunk;
        $this->length += strlen($chunk);
    }

    /**
     * Evaluate frames.
     *
     * @return array
     */
    public function evaluate(): iterable
    {
        if (null === $this->meta && $this->length >= Frame::HEADER_SIZE) {
            $header = substr($this->buffer, 0, Frame::HEADER_SIZE);
            $this->meta = self::parseHeader($header);
            $this->buffer = substr($this->buffer, Frame::HEADER_SIZE); // reset buffer
            $this->length -= strlen($header);
        }

        if (null !== $this->meta && $this->length >= $this->meta['size']) {
            $body = substr($this->buffer, 0, $this->meta['size']);
            $payload = self::parsePayload($this->meta['flags'], $body);
            $frame = new Frame($this->meta['type'], $this->meta['flags'], $payload);
            $this->buffer = substr($this->buffer, $this->meta['size']); // reset buffer
            $this->length -= strlen($body);
            $this->meta = null;

            yield $frame;

            // maybe buffer contains 2+ frame.
            if ($this->length >= Frame::HEADER_SIZE && ($rest = $this->evaluate())) {
                yield from $rest;
            }
        }
    }


    /**
     * Parse message header.
     *
     * @param string $header
     * @return array
     */
    private static function parseHeader(string $header): array
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
    private static function parsePayload(int $flags, string $body): array|string
    {
        if (($flags & Frame::PAYLOAD_JSON) === Frame::PAYLOAD_JSON) {
            return json_decode($body, true);
        }
        if (($flags & Frame::PAYLOAD_NONE) === Frame::PAYLOAD_NONE) {
            return "";
        }
        return $body;
    }
}