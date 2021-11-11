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

namespace FastServer\Bridge;

use FastServer\ParserInterface;

final class MessageParser implements ParserInterface
{
    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * Buffer length.
     * @var int
     */
    protected $length = 0;

    /**
     * @var array
     */
    protected $meta;

    /**
     * {@inheritdoc}
     */
    public function push(string $chunk)
    {
        $this->buffer .= $chunk;
        $this->length += strlen($chunk);
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(): array
    {
        $messages = [];

        if (null === $this->meta && $this->length >= Message::HEADER_SIZE) {
            $header = substr($this->buffer, 0, Message::HEADER_SIZE);
            $this->meta = Message::parseHeader($header);
            $this->buffer = substr($this->buffer, Message::HEADER_SIZE); // reset buffer
            $this->length -= strlen($header);
        }

        if (null !== $this->meta && $this->length >= $this->meta['size']) {
            $body = substr($this->buffer, 0, $this->meta['size']);
            $payload = Message::parsePayload($body);
            $message = new Message($this->meta['flags'], $payload, $body);
            $this->buffer = substr($this->buffer, $this->meta['size']); // reset buffer
            $this->length -= strlen($body);
            $this->meta = null;

            $messages[] = $message;

            // maybe buffer contains 2+ message.
            if ($this->length >= Message::HEADER_SIZE && ($rest = $this->evaluate())) {
                $messages = array_merge($messages, $rest);
            }
        }

        return $messages;
    }
}