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

use React\Stream\DuplexStreamInterface;
use Viso\Cluster\Exception\RuntimeException;
use Viso\Parser\ParserInterface;

class StreamChannel implements ChannelInterface
{
    protected DuplexStreamInterface $stream;

    protected ParserInterface $parser;

    private ?\Closure $listener = null;

    /**
     * @param DuplexStreamInterface $stream
     */
    public function __construct(DuplexStreamInterface $stream)
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
    public function listen(callable $listener, bool $override = false): void
    {
        if (null !== $this->listener && !$override) {
            throw new RuntimeException('The channel is listened by other listeners');
        }
        if (null !== $this->listener) {
            $this->stream->removeListener('data', $this->listener);
        }
        $this->listener = function(string $chunk) use($listener){
            $this->parser->push($chunk);
            foreach ($this->parser->evaluate() as $frame){
                call_user_func($listener, $frame);
            }
        };
        $this->stream->on('data', $this->listener);
    }
}