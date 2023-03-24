<?php

//declare(ticks=1);
pcntl_async_signals(true);

pcntl_signal(SIGHUP, function () {
    echo '信号触发', PHP_EOL;
});

$i = 1;

while (true) {
    $i++;
//    echo $i, PHP_EOL;
    sleep(1);
}