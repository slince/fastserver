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

use FastServer\ConnectionAwareInterface;
use FastServer\Exception\InvalidArgumentException;
use React\Socket\ConnectionInterface;

final class ParserFactory implements ParserFactoryInterface
{
    /**
     * @var string
     */
    protected $parserClass;

    public function __construct(string $parserClass)
    {
        if (!class_exists($parserClass)) {
            throw new InvalidArgumentException(sprintf('The parser fqcn "%s" is not exists', $parserClass));
        }
        if (!is_subclass_of($parserClass, ParserInterface::class)) {
            throw new InvalidArgumentException(sprintf('The parser fqcn "%s" is not implements of %s', $parserClass, ParserInterface::class));
        }
        $this->parserClass = $parserClass;
    }

    /**
     * {@inheritdoc}
     */
    public function createParser(ConnectionInterface $connection): ParserInterface
    {
        $parser = new $this->parserClass($connection);
        if ($parser instanceof ConnectionAwareInterface) {
            $parser->setConnection($connection);
        }
        return $parser;
    }
}