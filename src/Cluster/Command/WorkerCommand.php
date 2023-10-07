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

use Waveman\Channel\CommandInterface;

abstract class WorkerCommand implements CommandInterface
{
    private int $workerId;

    public function __construct(int $workerId)
    {
        $this->workerId = $workerId;
    }

    /**
     * Return the worker id
     * @return int
     */
    public function getWorkerId(): int
    {
        return $this->workerId;
    }
}