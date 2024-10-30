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

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class Logger implements LoggerInterface
{
    use LoggerTrait;

    private Cluster $cluster;
    private LoggerInterface $decorated;

    public function __construct(Cluster $cluster, LoggerInterface $decorated)
    {
        $this->cluster = $cluster;
        $this->decorated = $decorated;
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->decorated->log($level, $message, $context + $this->buildContext());
    }

    private function buildContext(): array
    {
        return $this->cluster->primary ? [
            'primary' => $this->cluster->primary,
            'pid' => getmypid()
        ] : [
            'primary' => $this->cluster->primary,
            'worker_id' => $this->cluster->worker?->getId(),
            'worker_pid' => getmypid()
        ];
    }
}