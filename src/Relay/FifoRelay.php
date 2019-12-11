<?php

namespace FastServer\Relay;

class FifoRelay extends StreamRelay
{
    public function __construct($input, $output)
    {
        parent::__construct($input, $output);
    }
}