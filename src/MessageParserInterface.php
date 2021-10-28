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

namespace FastServer;

interface MessageParserInterface
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
     * @return array
     */
    public function evaluate(): array;
}