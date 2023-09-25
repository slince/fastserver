<?php

namespace Waveman\Server\Channel;

use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;
use Waveman\Server\Exception\RuntimeException;

final class UnixSocketChannel extends StreamChannel
{
    public function __construct(LoopInterface $loop, bool $inChild, CommandFactoryInterface $commandFactory)
    {
        $stream = $this->createStream($loop, $inChild);
        parent::__construct($stream, $commandFactory);
    }

    private function createStream(LoopInterface $loop, bool $inChild): DuplexResourceStream
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            throw new RuntimeException('Cannot create socket pairs.');
        }
        if ($inChild) {
            fclose($sockets[1]);
            $stream = $sockets[0];
        } else {
            fclose($sockets[0]);
            $stream = $sockets[1];
        }
        stream_set_blocking($stream, false);
        return new DuplexResourceStream($stream, $loop);
    }
}