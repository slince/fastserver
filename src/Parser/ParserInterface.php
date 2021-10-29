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

use React\Promise\PromiseInterface;

interface ParserInterface
{
    /**
     * Push incoming data to the parser.
     *
     * @param string $chunk
     */
    public function push(string $chunk);

    /**
     * Evaluate messages.
     *
     * @return PromiseInterface
     */
    public function evaluate(): PromiseInterface;
}