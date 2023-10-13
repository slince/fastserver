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
namespace Waveman\Cluster;

use Evenement\EventEmitter;
use React\Socket\SocketServer;
use Slince\Process\Process;
use Waveman\Cluster\Exception\LogicException;
use Waveman\Cluster\Exception\RuntimeException;

final class Cluster extends EventEmitter
{
    public const WAVE_MAN_PID = 'X_WAVE_MAN_PID';
    public const WAVE_MAN_WORKER_ID = 'X_WAVE_MAN_WID';

    public bool $isPrimary;

    public ?Worker $worker = null;

    public WorkerPool $workers;

    private int $id = 0;

    private array $signals = [];

    private static ?Cluster $instance = null;

    private static bool $frozen = false;

    private function __construct(callable $callback = null)
    {
        $this->isPrimary = getenv(self::WAVE_MAN_PID) === false;
        $this->workers = WorkerPool::createPool($this, $callback);

        if (!$this->isPrimary) {
            $workerId = getenv(self::WAVE_MAN_WORKER_ID) ?? 0;
            $this->worker = $this->workers->create($workerId);
        }
    }

    /**
     * Creates a cluster instance.
     *
     * @param callable|null $callback
     * @return Cluster
     */
    public static function create(callable $callback = null): Cluster
    {
        if (self::$frozen) {
            throw new RuntimeException('Cluster can only be created once');
        }
        self::$frozen = true;
        return self::$instance = new Cluster($callback);
    }

    /**
     * Returns the cluster instance.
     * @return Cluster
     */
    public static function get(): Cluster
    {
        if (null === self::$instance) {
            self::$instance = new Cluster();
        }
        return self::$instance;
    }

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
     * Register signals handler for the cluster.
     *
     * @param int|array $signals
     * @param callable|int $handler
     * @return void
     */
    public function onSignals(int|array $signals, callable|int $handler): void
    {
        $this->requireInMainProcess(__METHOD__);
        if (is_array($signals)) {
            array_push($this->signals, ...$signals);
        } else {
            $this->signals[] = $signals;
        }
        Process::current()->signal($signals, $handler);
    }

    /**
     * Return the register signals.
     *
     * @return array
     */
    public function getSignals(): array
    {
        return $this->signals;
    }

    /**
     * Fork a worker.
     *
     * @return Worker
     */
    public function fork(): Worker
    {
        $this->requireInMainProcess(__METHOD__);
        return $this->workers->start($this->id ++);
    }

    /**
     * Run the cluster.
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->isPrimary) {
            if (Cluster::supportSignal()) {
                $this->onSignals(\SIGCHLD, function (){
                    $this->wait(false);
                });
            }
            $this->wait();
        } else {
            $this->worker->run();
        }
    }

    /**
     * Wait the workers exited.
     *
     * @param bool $blocking
     * @return void
     */
    public function wait(bool $blocking = true): void
    {
        $this->requireInMainProcess(__METHOD__);
        do {
            $closed = $this->workers->wait($blocking);
            foreach ($closed as $worker) {
                $this->emit('worker.close', [$worker]);
                $this->workers->remove($worker);
            }
            if ($this->workers->isEmpty()) {
                $this->emit('close');
                break;
            } else {
                usleep(2000);
            }
        } while ($blocking);
    }

    /**
     * Create a socket server by given address.
     *
     * @param string $address
     * @param array $context
     * @return SocketServer
     */
    public function listen(string $address, array $context = []): SocketServer
    {
        $context['tcp'] ??= [];
        $context['tcp']['so_reuseport'] = true;
        return new SocketServer($address, $context);
    }

    public function requireInChildProcess(string $method): void
    {
        if ($this->isPrimary) {
            throw new LogicException(sprintf('The method %s can only be executed in child process.', $method));
        }
    }

    public function requireInMainProcess(string $method): void
    {
        if (!$this->isPrimary) {
            throw new LogicException(sprintf('The method %s can only be executed in main process.', $method));
        }
    }
}