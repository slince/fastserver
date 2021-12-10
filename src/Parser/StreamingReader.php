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

namespace FastServer\Parser;

use Evenement\EventEmitter;
use FastServer\Exception\InvalidArgumentException;
use React\Stream\DuplexStreamInterface as Stream;

final class StreamingReader extends EventEmitter
{
    /**
     * @var ParserFactoryInterface
     */
    protected $parserFactory;

    public function __construct(ParserFactoryInterface $parserFactory)
    {
        $this->parserFactory = $parserFactory;
    }

    /**
     * Parse a message from stream end emit an event.
     * @param Stream $stream
     */
    public function listen(Stream $stream)
    {
        $parser = $this->parserFactory->createParser($stream);
        $writer = $this->parserFactory->createWriter($stream);
        try {
            $stream->on('data', function (string $chunk) use ($parser, $writer, $stream) {
                $parser->push($chunk);
                foreach ($parser->evaluate() as $message) {
                    $this->emit('message', [$message, $writer, $stream]);
                }
            });
        } catch (InvalidArgumentException $exception) {
            $this->emit('error', [$exception, $writer, $stream]);
        }
    }
}