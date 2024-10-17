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

    private LoggerInterface $decorated;

    public function __construct(LoggerInterface $decorated)
    {
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
        $cluster = Cluster::get();
        return $cluster->primary ? [
            'primary' => $cluster->primary,
            'pid' => getmypid()
        ] : [
            'primary' => $cluster->primary,
            'worker_id' => $cluster->worker?->getId(),
            'worker_pid' => $cluster->worker?->getPid(),
        ];
    }
}