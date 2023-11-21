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

namespace Viso\Cluster\Command;

use Viso\Channel\PayloadCommandInterface;
use Viso\Cluster\WorkerStatus;

final class StatusCommand extends WorkerCommand implements PayloadCommandInterface
{
    private WorkerStatus $status;

    public function __construct(int $workerId, WorkerStatus $status)
    {
        parent::__construct($workerId);
        $this->status = $status;
    }

    /**
     * @return WorkerStatus
     */
    public function getStatus(): WorkerStatus
    {
        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'STATUS';
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandKey(): string
    {
        return $this->getCommandId() . json_encode($this->status);
    }
}