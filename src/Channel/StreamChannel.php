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

    private bool $listened = false;

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
    public function listen(callable $listener, bool $once = false): void
    {
        if ($this->listened) {
            throw new RuntimeException('The channel is listened by other listeners');
        }
        $this->listened = true;
        $this->stream->on('data', function(string $chunk) use($listener){
            $this->parser->push($chunk);
            foreach ($this->parser->evaluate() as $frame){
                call_user_func($listener, $frame);
            }
        });
    }
}