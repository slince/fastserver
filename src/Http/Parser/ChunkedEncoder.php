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

namespace FastServer\Http\Parser;

class ChunkedEncoder
{
    public function change()
    {

    }

    /**
     * @param string $data - string to be transformed in a valid
     *                       HTTP encoded chunk string
     * @return string
     */
    protected function createChunk(string $data): string
    {
        $byteSize = \dechex(\strlen($data));
        $chunkBeginning = $byteSize . HttpParser::CRLF;

        return $chunkBeginning . $data . HttpParser::CRLF;
    }
}