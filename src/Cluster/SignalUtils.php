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
     * @return void
     */
    public static function registerSignals(int|array $signals, callable|int $handler): void
    {
        if (is_int($handler)) {
            Process::current()->signal($signals, $handler);
        } else {
            foreach ((array)$signals as $signal) {
                Loop::get()->addSignal($signal, $handler);
            }
        }
    }
}