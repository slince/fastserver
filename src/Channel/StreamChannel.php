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
use React\Stream\Util;
use Viso\Parser\ParserInterface;

class StreamChannel extends EventEmitter implements ChannelInterface
{
    protected DuplexStreamInterface $stream;

    protected ParserInterface $parser;

    /**
     * @param DuplexStreamInterface $stream
     */
    public function __construct(DuplexStreamInterface $stream)
    {
        $this->stream = $stream;
        $this->parser = new FrameParser();
        $this->stream->on('data', function(string $chunk){
            $this->parser->push($chunk);
            foreach ($this->parser->evaluate() as $frame) {
                $this->emit('message', [$frame]);
            }
        });
        Util::forwardEvents($this->stream, $this, ['error', 'close']);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Frame $frame): void
    {
        $message = Frame::pack($frame);
        $this->stream->write($message);
    }
}