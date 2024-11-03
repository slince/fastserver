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
namespace Viso\Channel;

final class FrameEncoder
{
    private static ?FrameEncoder $instance = null;

    /**
     * Pack the given frame.
     *
     * @param Frame $frame
     * @return string
     */
    public static function pack(Frame $frame): string
    {
        $flags = $frame->getFlags();
        if (($flags & Frame::PAYLOAD_JSON) === Frame::PAYLOAD_JSON) {
            $payload = json_encode($frame->getPayload());
        } else if (($flags & Frame::PAYLOAD_RAW) === Frame::PAYLOAD_RAW) {
            $payload = $frame->getPayload();
        } else {
            $payload = '';
        }
        $size = strlen($payload);
        $body = pack('CCJ', $frame->getType(), $flags, $size);

        if (($flags & Frame::PAYLOAD_NONE) !== Frame::PAYLOAD_NONE) {
            $body .= $payload;
        }
        return $body;
    }

    /**
     * Create the frame encoder.
     *
     * @return FrameEncoder
     */
    public static function get(): FrameEncoder
    {
        if (null !== FrameEncoder::$instance) {
            return FrameEncoder::$instance;
        }
        return FrameEncoder::$instance = new FrameEncoder();
    }
}