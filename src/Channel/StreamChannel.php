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

namespace Waveman\Channel;

use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

class StreamChannel implements ChannelInterface
{
    /**
     * @var WritableStreamInterface|ReadableStreamInterface
     */
    protected WritableStreamInterface|ReadableStreamInterface $stream;

    protected CommandFactoryInterface $commandFactory;

    /**
     * @param WritableStreamInterface|ReadableStreamInterface $stream
     * @param CommandFactoryInterface $commandFactory
     */
    public function __construct(WritableStreamInterface|ReadableStreamInterface $stream, CommandFactoryInterface $commandFactory)
    {
        $this->stream = $stream;
        $this->commandFactory = $commandFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function send(CommandInterface $command): void
    {
        $message = $this->commandFactory->createMessage($command);
        $message = Frame::pack($message);
        $this->stream->write($message);
    }

    /**
     * {@inheritdoc}
     */
    public function listen(callable $callback): void
    {
        $parser = new FrameParser();
        $this->stream->once('data', function(string $chunk) use ($parser, $callback){
            $parser->push($chunk);
            foreach ($parser->evaluate() as $frame) {
                $command = $this->commandFactory->createCommand($frame);
                $callback($command);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function supports(CommandInterface $command): bool
    {
        return $this->commandFactory->supportCommand($command);
    }
}