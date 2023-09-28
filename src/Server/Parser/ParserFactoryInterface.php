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

namespace Waveman\Server\Parser;

use React\Socket\ConnectionInterface;
use React\Stream\DuplexStreamInterface as Stream;

interface ParserFactoryInterface
{
    /**
     * Creates parser instance.
     *
     * @param ConnectionInterface $stream
     * @return ParserInterface
     */
    public function createParser(Stream $stream): ParserInterface;

    /**
     * Creates writer instance.
     *
     * @param ConnectionInterface $stream
     * @return WriterInterface
     */
    public function createWriter(Stream $stream): WriterInterface;
}