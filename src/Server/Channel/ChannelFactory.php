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

namespace Waveman\Server\Channel;

use React\Stream\DuplexStreamInterface;

final class ChannelFactory
{
    /**
     * Creates the connection for the given stream.
     *
     * @param DuplexStreamInterface $stream
     * @return ChannelInterface
     */
    public static function createChannel(DuplexStreamInterface $stream): ChannelInterface
    {
        return new StreamChannel($stream);
    }
}