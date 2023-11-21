<?php

namespace Viso\Server;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Slince\Process\Process;
use Viso\Cluster\Cluster;

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
            'pid' => Process::current()->pid()
        ] : [
            'primary' => $cluster->primary,
            'worker_id' => $cluster->worker?->getId(),
            'worker_pid' => $cluster->worker?->getPid(),
        ];
    }
}