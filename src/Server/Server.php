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

namespace Waveman\Server;

use Evenement\EventEmitter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Waveman\Channel\CommandInterface;
use Waveman\Cluster\Cluster;
use Waveman\Cluster\Command\CloseCommand;
use Waveman\Cluster\ConnectionMetadata;
use Waveman\Cluster\ConnectionPool;
use Waveman\Cluster\Worker;
use Waveman\Server\Command\ReloadCommand;
use Waveman\Server\Exception\InvalidArgumentException;
use Waveman\Server\Exception\RuntimeException;

final class Server extends EventEmitter implements ServerInterface
{
    private const EVENT_NAMES = ['start', 'close', 'error', 'command', 'connection', 'worker.start', 'worker.close'];

    /**
     * process status,running
     * @var string
     */
    const STATUS_READY = 'ready';

    /**
     * process status,running
     * @var string
     */
    const STATUS_STARTED = 'started';

    /**
     * closing.
     */
    const STATUS_CLOSING = 'closing';

    /**
     * process status,terminated
     * @var string
     */
    const STATUS_TERMINATED = 'terminated';

    /**
     * @var string
     */
    protected string $status = self::STATUS_READY;

    /**
     * @var array
     */
    private array $options;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ConnectionPool<ConnectionInterface, ConnectionMetadata>
     */
    private ConnectionPool $connections;

    private Cluster $cluster;

    public function __construct(array $options, ?LoggerInterface $logger = null)
    {
        $this->configure($options);
        $this->logger = $logger ?? new NullLogger();
        $this->connections = new ConnectionPool();
    }

    /**
     * Configure the server.
     *
     * @param array $options
     */
    private function configure(array $options): void
    {
        $optionsResolver = new OptionsResolver();
        $this->configureOptions($optionsResolver);
        $this->options = $optionsResolver->resolve($options);
        foreach ($this->options['plugins'] as $plugin) {
            $this->configurePlugin($plugin, $options[$plugin->getId()] ?? []);
        }
    }

    private function configurePlugin(PluginInterface $plugin, array $options): void
    {
        $optionsResolver = new OptionsResolver();
        $plugin->configureOptions($optionsResolver);
        $resolved = $optionsResolver->resolve($options);
        $plugin->configure($resolved);
    }

    /**
     * Returns the connection pool of the server.
     *
     * @return ConnectionPool
     */
    public function getConnections(): ConnectionPool
    {
        return $this->connections;
    }

    /**
     * Return the logger instance.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Configure options resolver for the server.
     *
     * @param OptionsResolver $resolver
     */
    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['plugins' => []])
            ->setAllowedTypes('plugins', [PluginInterface::class . '[]'])
            ->setRequired(['address', 'worker_num'])
            ->setInfo('worker_num', 'The worker num of the server')
            ->setIgnoreUndefined()
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function on($event, callable $listener): void
    {
        if (!in_array($event, self::EVENT_NAMES)) {
            throw new InvalidArgumentException(sprintf('The event "%s" is not supported.', $event));
        }
        parent::on($event, $listener);
    }

    /**
     * {@inheritdoc}
     */
    public function close(bool $graceful = false): void
    {
        if ($this->status !== self::STATUS_STARTED) {
            throw new RuntimeException("The server is not running");
        }
        $this->cluster->workers->close($graceful);
        $this->status = $graceful ? self::STATUS_CLOSING : self::STATUS_TERMINATED;
    }

    /**
     * {@inheritdoc}
     */
    public function serve(): void
    {
        if ($this->status !== self::STATUS_READY) {
            throw new RuntimeException("The server is already running");
        }
        $this->boot();
        // Register signal handlers after workers created.
        $this->status = self::STATUS_STARTED;
        $this->logger->info(sprintf('The server is listen on %s', $this->options['address']));
        $this->emit('start', [$this]);
        $this->cluster->run();
    }

    private function boot(): void
    {
        $this->activatePlugins();
        $this->cluster = Cluster::create($this->createCallable());

        if ($this->cluster->isPrimary) {
            $this->setupPrimary();
        }
    }

    private function setupPrimary(): void
    {
        $this->cluster->on('worker.close', function (Worker $worker){
            if ($this->status === self::STATUS_STARTED) {
                $this->logger->debug(sprintf('Checked the worker %d has exited, restart a new worker', $worker->getPid()));
                $this->cluster->fork();
            } else if ($this->status === self::STATUS_CLOSING) {
                $this->logger->debug(sprintf('Checked the worker %d has exited', $worker->getPid()));
            }
        });

        $this->cluster->on('close', function (){
            $this->status = self::STATUS_TERMINATED;
            $this->logger->info('All workers have been closed and exit the server');
            $this->emit('close');
        });

        // Register signal handlers for the cluster.
        $this->cluster->onSignals(\SIGINT, function (){
            $this->handleCommand(new CloseCommand(false));
        });

        $this->cluster->onSignals(\SIGTERM, function (){
            $this->handleCommand(new CloseCommand(true));
        });

        $this->cluster->onSignals(\SIGUSR1, function (){
            $this->handleCommand(new ReloadCommand());
        });

        for ($i = 0; $i < $this->options['worker_num']; $i++) {
            $this->cluster->fork();
        }
    }

    private function createCallable(): \Closure
    {
        return function (Cluster $cluster) {
            $loop = Loop::get();
            $socket = $cluster->listen($this->options['address'], $this->options);

            // handle connection
            $socket->on('connection', function(ConnectionInterface $connection) use ($cluster){
                $this->logger->debug(sprintf('Worker [%s] [%s] Accept connection from %s', $cluster->worker->getId(), $cluster->worker->getPid(), $connection->getLocalAddress()));
                $connection->on('close', function() use($connection){
                    $this->connections->remove($connection);
                });
                $this->emit('connection', [$connection]);
            });

            // handler error
            $socket->on('error', function (\Exception $error) use ($cluster){
                $this->logger->error(sprintf('Worker [%s] [%s] Accept connection error %s', $cluster->worker->getId(), $cluster->worker->getPid(), $error));
                $this->emit('error', [$error]);
            });

            // when the worker received close command.
            $cluster->worker->on('close', function () use($loop){
                $this->connections->close();
                $loop->stop();
            });
            $loop->run();
        };
    }

    private function activatePlugins(): void
    {
        $this->logger->debug('Activate plugins.');
        foreach ($this->options['plugins'] as $plugin) {
            $plugin->activate($this);
        }
    }

    /**
     * Handle commands.
     *
     * {@internal}
     * @param CommandInterface $command
     * @return void
     */
    public function handleCommand(CommandInterface $command): void
    {
        $this->emit('command', [$command]);
        $this->logger->debug(sprintf('Received command %s', $command->getCommandId()), ['pid' => getmypid()]);
        switch ($command->getCommandId()) {
            case 'CLOSE':
                $this->logger->debug(sprintf('The server current status: %s', $this->status));
                if ($this->status === self::STATUS_STARTED) {
                    $this->close($command->isGraceful());
                }
                break;
            case 'RELOAD':
                $this->logger->debug('Reload workers.');
                $this->cluster->workers->restartAll();
                break;
            // Command from workers.
            case 'WORKER_PING':
                $this->logger->debug(sprintf('Received ping from worker %d.', $command->getWorkerId()));
                $this->cluster->workers->heartbeat($command->getWorkerId());
                break;
            default:
                $this->logger->debug(sprintf('Ignore command %s', $command->getCommandId()));
        }
    }
}