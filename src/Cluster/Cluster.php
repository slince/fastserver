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

use Evenement\EventEmitter;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Socket\SocketServer;
use Viso\Cluster\Exception\LogicException;
use Viso\Cluster\Exception\RuntimeException;
use Viso\Cluster\Worker\Worker;
use Viso\Cluster\Worker\WorkerPool;

final class Cluster extends EventEmitter
{
    public const VISO_PID = 'X_VISO_PID';
    public const VISO_WORKER_ID = 'X_VISO_WID';

    public bool $primary;

    public WorkerPool $workers;

    public ?Worker $worker = null;

    public LoopInterface $loop;

    private int $id = 0;

    private array $signals = [];

    private static ?Cluster $instance = null;

    private static bool $frozen = false;

    private function __construct(callable $callback)
    {
        $this->primary = getenv(self::VISO_PID) === false;
        $this->workers = WorkerPool::createPool($this, $callback);

        if (!$this->primary) {
            $workerId = getenv(self::VISO_WORKER_ID) ?? 0;
            $this->worker = $this->workers->create($workerId);
            $this->loop = Loop::get();
        } else {
            $this->loop = new StreamSelectLoop();
        }
    }

    /**
     * Creates a cluster instance.
     *
     * @param callable $callback
     * @return Cluster
     */
    public static function create(callable $callback): Cluster
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
            throw new RuntimeException('Please create cluster before get');
        }
        return self::$instance;
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
        $this->signals = array_unique(array_merge($this->signals, (array)$signals));
        SignalUtils::registerSignals($signals, $handler, $this->loop);
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
        if ($this->primary) {
            $this->loop->addPeriodicTimer(1, function(){
                $this->wait();
            });
            if (SignalUtils::supportSignal()) {
                $this->onSignals(\SIGCHLD, function (){
                    $this->wait();
                });
            }
            $this->loop->run();
        } else {
            $this->worker->run();
        }
    }

    /**
     * Wait the workers exited.
     *
     * @return void
     */
    public function wait(): void
    {
        $this->requireInMainProcess(__METHOD__);
        $closed = $this->workers->wait();
        $hasWorkerExited = iterator_count($closed) > 0;
        if ($hasWorkerExited && $this->workers->isEmpty()) {
            $this->emit('close');
            $this->loop->stop();
        }
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
        if ($this->primary) {
            throw new LogicException(sprintf('The method %s can only be executed in child process.', $method));
        }
    }

    public function requireInMainProcess(string $method): void
    {
        if (!$this->primary) {
            throw new LogicException(sprintf('The method %s can only be executed in main process.', $method));
        }
    }
}