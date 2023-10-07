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

namespace Waveman\Channel;

use Waveman\Server\Parser\ParserInterface;

final class FrameParser implements ParserInterface
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
    protected ?array $meta = [];

    /**
     * {@inheritdoc}
     */
    public function push(string $chunk): void
    {
        $this->buffer .= $chunk;
        $this->length += strlen($chunk);
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(): array
    {
        $frames = [];

        if (null === $this->meta && $this->length >= Frame::HEADER_SIZE) {
            $header = substr($this->buffer, 0, Frame::HEADER_SIZE);
            $this->meta = Frame::parseHeader($header);
            $this->buffer = substr($this->buffer, Frame::HEADER_SIZE); // reset buffer
            $this->length -= strlen($header);
        }

        if (null !== $this->meta && $this->length >= $this->meta['size']) {
            $body = substr($this->buffer, 0, $this->meta['size']);
            $payload = Frame::parsePayload($this->meta['flags'], $body);
            $frame = new Frame($this->meta['type'], $this->meta['flags'], $payload);
            $this->buffer = substr($this->buffer, $this->meta['size']); // reset buffer
            $this->length -= strlen($body);
            $this->meta = null;

            $frames[] = $frame;

            // maybe buffer contains 2+ frame.
            if ($this->length >= Frame::HEADER_SIZE && ($rest = $this->evaluate())) {
                $frames = array_merge($frames, $rest);
            }
        }

        return $frames;
    }
}