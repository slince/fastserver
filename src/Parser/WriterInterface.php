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

namespace Viso\Parser;

interface WriterInterface
{
    /**
     * Writes message to the stream.
     *
     * @param mixed $response response message.
     * @param mixed|null $request request message.
     */
    public function write(mixed $response, mixed $request = null);
}