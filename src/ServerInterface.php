<?php

namespace FastServer;

interface ServerInterface
{
    public function configure(array $options);

    public function on($eventName, callable  $listener);

    public function serve();
}