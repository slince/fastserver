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

use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use Viso\Parser\ParserInterface;

class StreamChannel implements ChannelInterface
{
    /**
     * @var WritableStreamInterface|ReadableStreamInterface
     */
    protected WritableStreamInterface|ReadableStreamInterface $stream;

    protected ParserInterface $parser;

    /**
     * @param WritableStreamInterface|ReadableStreamInterface $stream
     */
    public function __construct(WritableStreamInterface|ReadableStreamInterface $stream)
    {
        $this->stream = $stream;
        $this->parser = new FrameParser();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Frame $frame): void
    {
        $message = Frame::pack($frame);
        $this->stream->write($message);
    }

    /**
     * {@inheritdoc}
     */
    public function listen(callable $callback): void
    {
        $this->stream->once('data', function(string $chunk) use ($callback){
            $this->parser->push($chunk);
            foreach ($this->parser->evaluate() as $frame) {
                $callback($frame);
            }
        });
    }
}