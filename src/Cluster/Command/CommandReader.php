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
namespace Viso\Cluster\Command;

use Evenement\EventEmitter;
use Viso\Channel\ChannelInterface;
use Viso\Channel\Frame;

final class CommandReader extends EventEmitter
{
    private ChannelInterface $channel;

    private CommandFactoryInterface $commandFactory;

    /**
     * @param ChannelInterface $channel
     * @param CommandFactoryInterface $commandFactory
     */
    public function __construct(ChannelInterface $channel, CommandFactoryInterface $commandFactory)
    {
        $this->channel = $channel;
        $this->commandFactory = $commandFactory;

        $this->channel->listen(function(Frame $frame) {
            $command = $this->commandFactory->createCommand($frame);
            $this->emit('command', [$command]);
        });
    }
}