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
namespace Viso\Channel;

interface PayloadCommandInterface extends CommandInterface
{
    /**
     * Return the command key.
     *
     * @return string
     */
    public function getCommandKey(): string;
}