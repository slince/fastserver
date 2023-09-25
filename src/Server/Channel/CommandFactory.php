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

namespace Waveman\Server\Channel;

use Waveman\Server\Channel\Command\CommandInterface;
use Waveman\Server\Exception\BadMessageException;

final class CommandFactory implements CommandFactoryInterface
{
    protected array $commands = [];

    public function __construct(array $commands)
    {
        $this->addCommands($commands);
    }

    /**
     * Adds commands
     * @param array $commands
     * @return void
     */
    public function addCommands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->commands[$command->getCommandId()] = $command;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createCommand(Message $message): CommandInterface
    {
        $commandId = $message->getArgument('_cid_');
        if (!isset($this->commands[$commandId])) {
            throw new BadMessageException('Cannot find the command id from the message');
        }
        return call_user_func([$this->commands[$commandId], 'fromMessage'], $message);
    }
}