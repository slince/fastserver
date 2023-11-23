<?php

namespace Viso\Cluster;

use Viso\Channel\ChannelInterface;
use Viso\Cluster\Command\CommandFactoryInterface;
use Viso\Cluster\Command\CommandInterface;

final class Relay
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
    }

    public function send(CommandInterface $command): void
    {
        $frame = $this->commandFactory->createFrame($command);
        $this->channel->send($frame);
    }
}