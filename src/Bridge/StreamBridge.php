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

namespace FastServer\Bridge;

use React\Stream\DuplexStreamInterface;
use FastServer\Bridge\Command\CommandInterface;

class StreamBridge implements BridgeInterface
{
    /**
     * @var DuplexStreamInterface
     */
    protected $stream;

    public function __construct(DuplexStreamInterface $stream)
    {
        $this->stream = $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function executeCommand(CommandInterface $command)
    {
        $message = $command->createMessage();
        $message->addArgument('_cid_', $command->getCommandId());
        $message = Message::pack($message);
        $this->stream->write($message);
    }

    /**
     * {@inheritdoc}
     */
    public function listen(callable $callback)
    {
        $parser = new MessageParser();
        $this->stream->once('data', function(string $chunk) use ($parser, $callback){
            $parser->push($chunk);
            foreach ($parser->evaluate() as $message) {
                $callback($message, $this);
            }
        });
    }
}