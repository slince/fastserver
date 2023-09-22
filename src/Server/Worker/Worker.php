<?php

declare(strict_types=1);

/*
 * This file is part of the fastserver/fastserver package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Waveman\Server\Worker;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Waveman\Server\ServerInterface;

class Worker
{
    /**
     * @var int
     */
    protected int $id;

    /**
     * @var ServerInterface
     */
    protected ServerInterface $server;

    /**
     * @var LoopInterface
     */
    protected LoopInterface $loop;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    public function __construct(int $id, ServerInterface $server, LoggerInterface $logger, LoopInterface $loop)
    {
        $this->id = $id;
        $this->server = $server;
        $this->logger = $logger;
        $this->loop = $loop;
    }

    /**
     * Return the worker id.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Return the worker pid.
     *
     * @return int
     */
    public function getPid(): int
    {
        return getmypid();
    }

    /**
     * Starts the worker.
     */
    public function start(): void
    {
        if ($this->server->getOption('reuseport')) {
            $socket = $this->server->createSocket();
        } else {
            $socket = $this->server->getSocket();
        }
        $server = $this->server->createSocketServer($socket, $this->loop);
        $server->on('connection', [$this->server, 'handleConnection']);
        $server->on('error', [$this->server, 'handleError']);
    }

    /**
     * Close the worker.
     *
     * {@internal}
     */
    public function close(bool $grace = false): void
    {
        // ignore this
    }
}