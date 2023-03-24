<?php


$begin = [1,2,3];

$pid = pcntl_fork();

if ($pid > 0) {
    echo 'i m parent', PHP_EOL;
} elseif ($pid === 0) {
    echo 'i m children', PHP_EOL;
    sleep(12000);
    exit;
}

echo 'i m wrapper';
sleep(120000);
