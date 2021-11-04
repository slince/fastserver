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

namespace FastServer\Worker;

use parallel\Runtime as ParallelRuntime;
use parallel\Channel;

class ParallelWorker extends Worker
{
    /**
     * @var ParallelRuntime
     */
    protected $runtime;

    public function start()
    {
        $channel = new Channel();
        $this->runtime = new ParallelRuntime();
        $this->runtime->run($this->createCallable(), [$channel]);
    }

    protected function createCallable(): \Closure
    {
        return function(Channel $channel){
            $this->work();
        };
    }
}