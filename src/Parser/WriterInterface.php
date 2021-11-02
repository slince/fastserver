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

interface WriterInterface
{
    /**
     * Writes message to the stream.
     *
     * @param mixed $response response message.
     * @param mixed $request request message.
     */
    public function write($response, $request = null);
}