<?php

namespace FastServer\Parser;

use Evenement\EventEmitter;
use FastServer\Exception\InvalidArgumentException;
use React\Stream\DuplexStreamInterface;

class StreamingReader extends EventEmitter
{
    /**
     * @var ParserFactoryInterface
     */
    protected $parserFactory;

    public function __construct(ParserFactoryInterface $parserFactory)
    {
        $this->parserFactory = $parserFactory;
    }

    public function listen(DuplexStreamInterface $stream)
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