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

namespace Viso\Cluster\Command;

interface CommandInterface
{
    /**
     * Gets the command id.
     *
     * @return string
     */
    public function getCommandId(): string;
}