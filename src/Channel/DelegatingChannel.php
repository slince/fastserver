<?php

namespace Waveman\Channel;

final class DelegatingChannel implements ChannelInterface
{
    /**
     * @var ChannelInterface[]
     */
    private array $channels;

    public function __construct(array $channels = [])
    {
        $this->channels = $channels;
    }

    /**
     * {@inheritdoc}
     */
    public function send(CommandInterface $command): void
    {
        foreach ($this->channels as $channel) {
            if ($channel->supports($command)) {
                $channel->send($command);
                break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function listen(callable $callback): void
    {
        foreach ($this->channels as $channel) {
            $channel->listen($callback);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(CommandInterface $command): bool
    {
        foreach ($this->channels as $channel) {
            if ($channel->supports($command)) {
                return true;
            }
        }
        return false;
    }
}