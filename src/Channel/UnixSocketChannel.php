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

use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;
use Waveman\Server\Exception\RuntimeException;

final class UnixSocketChannel extends StreamChannel
{
    public function __construct(array $sockets, LoopInterface $loop, bool $inChild, CommandFactoryInterface $commandFactory)
    {
        $stream = $this->createStream($sockets, $loop, $inChild);
        parent::__construct($stream, $commandFactory);
    }

    public static function createSocketPair(): array
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            throw new RuntimeException('Cannot create socket pairs.');
        }
        return $sockets;
    }

    public static function createStream(array $sockets, LoopInterface $loop, bool $inChild): DuplexResourceStream
    {
        if ($inChild) {
            fclose($sockets[1]);
            $stream = $sockets[0];
        } else {
            fclose($sockets[0]);
            $stream = $sockets[1];
        }
        return new DuplexResourceStream($stream, $loop);
    }
}