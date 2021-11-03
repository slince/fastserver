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

use FastServer\StreamAwareInterface;
use FastServer\Exception\InvalidArgumentException;
use React\Socket\ConnectionInterface;
use React\Stream\DuplexStreamInterface;

final class ParserFactory implements ParserFactoryInterface
{
    /**
     * @var string
     */
    protected $parserClass;

    /**
     * @var string
     */
    protected $writerClass;

    public function __construct(string $parserClass, string $writerClass)
    {
        if (!class_exists($parserClass)) {
            throw new InvalidArgumentException(sprintf('The parser fqcn "%s" is not exists', $parserClass));
        }
        if (!is_subclass_of($parserClass, ParserInterface::class)) {
            throw new InvalidArgumentException(sprintf('The parser fqcn "%s" is not implements of %s', $parserClass, ParserInterface::class));
        }
        if (!class_exists($writerClass)) {
            throw new InvalidArgumentException(sprintf('The writer fqcn "%s" is not exists', $writerClass));
        }
        if (!is_subclass_of($writerClass, WriterInterface::class)) {
            throw new InvalidArgumentException(sprintf('The writer fqcn "%s" is not implements of %s', $writerClass, WriterInterface::class));
        }
        $this->parserClass = $parserClass;
        $this->writerClass = $writerClass;
    }

    /**
     * {@inheritdoc}
     */
    public function createParser(DuplexStreamInterface $stream): ParserInterface
    {
        $parser = new $this->parserClass($stream);
        if ($parser instanceof StreamAwareInterface) {
            $parser->setStream($stream);
        }
        return $parser;
    }

    /**
     * {@inheritdoc}
     */
    public function createWriter(DuplexStreamInterface $stream): WriterInterface
    {
        $writer = new $this->writerClass($stream);
        if ($writer instanceof StreamAwareInterface) {
            $writer->setStream($stream);
        }
        return $writer;
    }
}