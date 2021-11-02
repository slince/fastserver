<?php

namespace FastServer\Http;

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