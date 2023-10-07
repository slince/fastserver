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