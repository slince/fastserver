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

use Waveman\Channel\PayloadCommandInterface;

final class CloseCommand implements PayloadCommandInterface
{
    /**
     * Close gracefully
     * @var bool
     */
    protected bool $graceful = false;

    public function __construct(bool $graceful)
    {
        $this->graceful = $graceful;
    }

    /**
     * @return bool
     */
    public function isGraceful(): bool
    {
        return $this->graceful;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandId(): string
    {
        return 'CLOSE';
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandKey(): string
    {
        return $this->getCommandId() . '_' . $this->isGraceful();
    }
}