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

interface ParserInterface
{
    /**
     * Push incoming data to the parser.
     *
     * @param string $chunk
     */
    public function push(string $chunk): void;

    /**
     * Evaluate messages.
     *
     * @return array
     */
    public function evaluate(): iterable;
}