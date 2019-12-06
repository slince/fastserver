<?php

namespace FastServer\Relay;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Promise;
use HttpServer\Exception\TransportException;

class AsyncStreamRelay implements RelayInterface, AsyncRelayInterface
{
    /**
     * @var InputStream
     */
    protected $input;

    /**
     * @var InputStream
     */
    protected $output;

    public function __construct($input, $output)
    {
        $this->input = new ResourceInputStream($input, static::BUFFER_SIZE);
        $this->output = new ResourceOutputStream($output, static::BUFFER_SIZE);
    }

    public function sendAsync($payload, int $flags = null): Promise
    {
        $size = strlen($payload);
        if ($flags & self::PAYLOAD_NONE && $size != 0) {
            throw new TransportException("unable to send payload with PAYLOAD_NONE flag");
        }
        $body = pack('CPJ', $flags, $size, $size);
        if (!($flags & self::PAYLOAD_NONE)) {
            $body .= $payload;
        }
        return $this->output->write($body);
    }

    public function read()
    {
        
    }
}