<?php

use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;

include __DIR__ . '/../vendor/autoload.php';

//$res = stream_set_blocking(STDIN, false);

$stream = new ReadableResourceStream(STDIN);

$stream->on('data', function ($msg){
    var_dump("receive", $msg);
});

var_dump("hehe");
Loop::get()->run();