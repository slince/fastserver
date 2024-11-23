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

namespace Viso\Channel;

use Evenement\EventEmitter;
use React\Stream\DuplexStreamInterface;

class StreamChannel extends EventEmitter implements ChannelInterface
{
    protected FrameEncoder $encoder;
    protected FrameParser $parser;

    protected DuplexStreamInterface $stream;

    /**
     * @param DuplexStreamInterface $stream
     */
    public function __construct(DuplexStreamInterface $stream)
    {
        $this->encoder = FrameEncoder::get();
        $this->parser = new FrameParser();
        $this->stream = $stream;
        $this->stream->on('data', function(string $chunk){
            $this->parser->push($chunk);
            foreach ($this->parser->evaluate() as $frame){
                $this->emit('frame', [$frame]);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function send(Frame $frame): void
    {
        $message = $this->encoder->pack($frame);
        $this->stream->write($message);
    }
}