<?php

namespace FastServer\Server;

interface ServerInterface
{
    public function on($eventName, callable $listener);

    public function configure(array $options);

    public function serve();
}