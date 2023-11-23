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

namespace Viso\Server;

use Evenement\EventEmitter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Viso\Cluster\Cluster;
use Viso\Cluster\Command\CloseCommand;
use Viso\Cluster\Command\CommandInterface;
use Viso\Cluster\Command\ControlCommand;
use Viso\Cluster\Command\ReloadCommand;
use Viso\Cluster\ConnectionMetadata;
use Viso\Cluster\ConnectionPool;
use Viso\Server\Exception\InvalidArgumentException;
use Viso\Server\Exception\RuntimeException;

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

    /**
     * @var PluginInterface[]
     */
    private array $plugins;

    public function __construct(array $options, array $plugins = [], ?LoggerInterface $logger = null)
    {
        $this->plugins = $plugins;
        $this->logger = new Logger($logger ?? new NullLogger());
        $this->connections = new ConnectionPool();
        $this->configure($options);
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
    }

    /**
     * {@inheritdoc}
     */
    public function getConnections(): ConnectionPool
    {
        return $this->connections;
    }

    /**
     * {@inheritdoc}
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
            ->setRequired(['address', 'worker_num'])
            ->setInfo('worker_num', 'The worker num of the server')
        ;
        foreach ($this->plugins as $plugin) {
            $resolver->setDefault($plugin->getId(), function(OptionsResolver $resolver) use ($plugin){
                $plugin->configureOptions($resolver);
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    public function on($event, callable $listener): void
    {
        if (!in_array($event, self::EVENT_NAMES) && !$this->isSupportedEventInPlugins($event)) {
            throw new InvalidArgumentException(sprintf('The event "%s" is not supported.', $event));
        }
        parent::on($event, $listener);
    }

    /**
     * Checks whether the event is supported by plugins.
     * @param string $event
     * @return bool
     */
    private function isSupportedEventInPlugins(string $event): bool
    {
        foreach ($this->plugins as $plugin) {
            if (in_array($event, $plugin->getEvents())) {
                return true;
            }
        }
        return false;
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
            throw new RuntimeException('The server is already running.');
        }

        $this->cluster = Cluster::create($this->createSetupWorker());
        $this->activatePlugins();

        if ($this->cluster->primary) {
            $this->setupPrimary();
        }

        $this->status = self::STATUS_STARTED;
        $this->logger->info(sprintf('The server is listen on %s', $this->options['address']));
        $this->emit('start', [$this]);
        $this->cluster->run();
    }

    private function activatePlugins(): void
    {
        $this->logger->debug('Activate plugins.');
        foreach ($this->plugins as $plugin) {
            $plugin->activate($this, $this->options[$plugin->getId()]);
        }
    }

    private function setupPrimary(): void
    {
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
        $this->cluster->onSignals(\SIGQUIT, function (){
            $this->handleCommand(new ReloadCommand());
        });
        $this->cluster->onSignals(\SIGUSR1, function (){
            $this->handleCommand(new ControlCommand(ControlCommand::STATUS));
        });
        $this->cluster->onSignals(\SIGUSR2, function (){
            $this->handleCommand(new ControlCommand(ControlCommand::CONNECTIONS));
        });

        for ($i = 0; $i < $this->options['worker_num']; $i++) {
            $worker = $this->cluster->fork();
            $worker->on('start', function() use($worker){
                $this->emit('worker.start', [$worker]);
            });
            $worker->on('close', function () use ($worker){
                $this->emit('worker.close', [$worker]);
                if ($this->status === self::STATUS_STARTED) {
                    $this->logger->warning(sprintf('Checked the worker %d has exited, restart a new worker', $worker->getPid()));
//                $this->cluster->fork();
                } else if ($this->status === self::STATUS_CLOSING) {
                    $this->logger->debug(sprintf('Checked the worker %d has exited', $worker->getPid()));
                }
            });
        }
    }

    private function createSetupWorker(): \Closure
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

            $worker = $cluster->worker;
            $worker->on('start', function() use($worker){
                $this->emit('worker.start', [$worker]);
            });
            // on worker close.
            $onClose = function () use ($worker, $loop){
                $this->emit('worker.close', [$worker]);
                $this->connections->close();
                $loop->stop();
            };
            // when the worker received close command.
            $worker->on('close', $onClose);
            $worker->onSignals([SIGINT, SIGTERM, SIGQUIT], $onClose);
            $loop->run();
        };
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
            case 'CONTROL':
                $this->logger->debug('Send control command to workers.');
                $this->cluster->workers->send($command);
                break;
            default:
                $this->logger->debug(sprintf('Ignore command %s', $command->getCommandId()));
        }
    }
}