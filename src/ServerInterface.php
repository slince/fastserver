<?php

namespace FastServer;

interface ServerInterface
{
    public function on($eventName, callable $listener);

    public function configure(array $options);

    public function serve();
}