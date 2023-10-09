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

use Waveman\Channel\Exception\InvalidArgumentException;

final class DelegatingCommandFactory implements CommandFactoryInterface
{
    /**
     * @var array<CommandFactoryInterface>
     */
    private array $commandFactories;

    public function __construct(array $commandFactories = [])
    {
        $this->commandFactories = $commandFactories;
    }

    /**
     * {@inheritdoc}
     */
    public function createFrame(CommandInterface $command): Frame
    {
        foreach ($this->commandFactories as $commandFactory) {
            if ($commandFactory->supportCommand($command)) {
                return $commandFactory->createFrame($command);
            }
        }
        throw new InvalidArgumentException(sprintf('The command %s is not supported', $class));
    }

    /**
     * {@inheritdoc}
     */
    public function supportCommand(CommandInterface $command): bool
    {
        foreach ($this->commandFactories as $commandFactory) {
            if ($commandFactory->supportCommand($command)) {
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function createCommand(Frame $frame): CommandInterface
    {
        foreach ($this->commandFactories as $commandFactory) {
            try {
                return $commandFactory->createCommand($frame);
            } catch (InvalidArgumentException $e) {
            }
        }
        throw new InvalidArgumentException(sprintf('The command type %d is not supported', $frame->getType()));
    }
}