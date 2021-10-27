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
use React\EventLoop\LoopInterface;
use FastServer\ServerInterface;

class ParallelWorker extends Worker
{
    protected $runtime;

    public function __construct(LoopInterface $loop, ServerInterface $server)
    {
        parent::__construct($loop, $server);
    }

    public function start()
    {
        $this->runtime = new ParallelRuntime();
        $this->runtime->run($this->createCallable());
    }

    protected function createCallable(): \Closure
    {
        return function($id, Channel $channel){

            $this->work();
        };
    }
}