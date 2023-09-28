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

interface CommandInterface
{
    /**
     * Gets the command id.
     *
     * @return string
     */
    public function getCommandId(): string;

    /**
     * Return the command key.
     *
     * @return string
     */
    public function getCommandKey(): string;
}