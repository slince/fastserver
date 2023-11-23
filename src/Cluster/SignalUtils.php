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
namespace Viso\Cluster;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Slince\Process\Process;

final class SignalUtils
{
    /**
     * Checks whether support signal.
     *
     * @return bool
     */
    public static function supportSignal(): bool
    {
        return Process::isSupportPosixSignal();
    }

    /**
     * Register signal handlers for the current process.
     *
     * @param int|array $signals
     * @param callable|int $handler
     * @param LoopInterface|null $loop
     * @return void
     */
    public static function registerSignals(int|array $signals, callable|int $handler, ?LoopInterface $loop = null): void
    {
        if (is_int($handler)) {
            Process::current()->signal($signals, $handler);
        } else {
            $loop = $loop ?: Loop::get();
            foreach ((array)$signals as $signal) {
                $loop->addSignal($signal, $handler);
            }
        }
    }
}