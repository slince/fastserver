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

use React\Socket\ConnectionInterface;
use React\Stream\DuplexStreamInterface;

interface ParserFactoryInterface
{
    /**
     * Creates parser instance.
     *
     * @param ConnectionInterface $stream
     * @return ParserInterface
     */
    public function createParser(DuplexStreamInterface $stream): ParserInterface;

    /**
     * Creates writer instance.
     *
     * @param ConnectionInterface $stream
     * @return WriterInterface
     */
    public function createWriter(DuplexStreamInterface $stream): WriterInterface;
}