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

namespace Waveman\Cluster\Command;

final class WorkerPingCommand extends WorkerCommand
{
    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'PING';
    }
}