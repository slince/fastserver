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
namespace Waveman\Cluster\Command;

use Waveman\Channel\CommandInterface;

final class MessageCommand implements CommandInterface
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    /**
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'NOP';
    }
}