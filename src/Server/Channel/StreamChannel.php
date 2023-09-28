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

namespace Waveman\Server\Channel;

use React\Stream\DuplexStreamInterface;

class StreamChannel implements ChannelInterface
{
    /**
     * @var DuplexStreamInterface
     */
    protected DuplexStreamInterface $stream;

    protected CommandFactoryInterface $commandFactory;

    public function __construct(DuplexStreamInterface $stream, CommandFactoryInterface $commandFactory)
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
        $message = Message::pack($message);
        $this->stream->write($message);
    }

    /**
     * {@inheritdoc}
     */
    public function listen(callable $callback): void
    {
        $parser = new MessageParser();
        $this->stream->once('data', function(string $chunk) use ($parser, $callback){
            $parser->push($chunk);
            foreach ($parser->evaluate() as $message) {
                $command = $this->commandFactory->createCommand($message);
                $callback($command);
            }
        });
    }
}