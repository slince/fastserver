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

namespace Waveman\Parser;

use React\Stream\DuplexStreamInterface as Stream;
use Waveman\Server\Exception\InvalidArgumentException;
use Waveman\Server\StreamAwareInterface;

final class ParserFactory implements ParserFactoryInterface
{
    /**
     * @var string
     */
    protected string $parserClass;

    /**
     * @var string
     */
    protected string $writerClass;

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
    public function createParser(Stream $stream): ParserInterface
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
    public function createWriter(Stream $stream): WriterInterface
    {
        $writer = new $this->writerClass($stream);
        if ($writer instanceof StreamAwareInterface) {
            $writer->setStream($stream);
        }
        return $writer;
    }
}