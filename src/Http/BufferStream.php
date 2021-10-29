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

namespace FastServer\Parser;

use React\Promise\Deferred;

class BufferStream
{
    /**
     * @var string
     */
    protected $buffer;

    /**
     * Buffer length.
     * @var int
     */
    protected $length = 0;

    /**
     * Push incoming data to stream.
     *
     * @param string $chunk
     */
    public function push(string $chunk)
    {
        $this->buffer .= $chunk;
        $this->length += strlen($chunk);
    }

    public function readUntil(string $char)
    {
        $deferred = new Deferred();
        $callable = function () {

        };
        return $deferred->promise();
    }

    public function readBytes(int $length)
    {
        $deferred = new Deferred();
        return $deferred->promise();
    }
}