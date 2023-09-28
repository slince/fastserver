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

namespace Waveman\Server\Command\Handler;

use Waveman\Server\Channel\ChannelInterface;
use Waveman\Server\Channel\CommandInterface;
use Waveman\Server\Exception\InvalidArgumentException;

final class DelegatingHandler implements HandlerInterface
{
    /**
     * @var HandlerResolver
     */
    protected HandlerResolver $resolver;

    public function __construct(HandlerResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(CommandInterface $command, ChannelInterface $connection)
    {
        if (null === $loader = $this->resolver->resolve($command)) {
            throw new InvalidArgumentException(sprintf('Cannot find handler for command type: "%s"',
                get_class($command)
            ));
        }

        return $loader->handle($command, $connection);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(CommandInterface $command): bool
    {
        return null !== $this->resolver->resolve($command);
    }
}