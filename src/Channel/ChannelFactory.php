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

use React\Stream\DuplexStreamInterface as Stream;

final class ChannelFactory
{
    /**
     * Creates the connection for the given stream.
     *
     * @param Stream $stream
     * @param CommandFactoryInterface $commandFactory
     * @return ChannelInterface
     */
    public static function createStreamChannel(Stream $stream, CommandFactoryInterface $commandFactory): ChannelInterface
    {
        return new StreamChannel($stream, $commandFactory);
    }
}