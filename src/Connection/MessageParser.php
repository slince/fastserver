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

namespace FastServer\Connection;

final class MessageParser
{
    protected $callback;

    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * Buffer length.
     * @var int
     */
    protected $length = 0;

    protected $meta;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    protected function push(string $chunk)
    {
        $this->buffer .= $chunk;
        $this->length += strlen($chunk);
        $this->evaluate();
    }

    public function evaluate()
    {
        if (null === $this->meta) {

        }
        if (null === $this->meta && $this->buffer >= Message::HEADER_SIZE) {
            $meta = Message::parseHeader(substr($this->buffer, 0, Message::HEADER_SIZE));
            $buffer = substr($this->buffer, Message::HEADER_SIZE); // reset buffer
            $readSize = strlen($buffer);
        }
        if (null !== $meta && $readSize >= $meta['size']) {
            $body = substr($buffer, 0, $meta['size']);
            $payload = Message::parsePayload($body);
            $message = new Message($meta['flags'], $payload, $body);
            $this->connection->emit('message', [$message, $this->connection, $meta]);
            $buffer = substr($buffer, $meta['size']); // reset buffer
            $readSize = strlen($buffer);
            $meta = null;

            // maybe buffer contains 2+ message.
            if ($readSize >= Message::HEADER_SIZE) {
                $this->connection->emit('data', ['']);
            }
        }
    }
}