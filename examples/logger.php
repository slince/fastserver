<?php

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

include __DIR__ . '/../vendor/autoload.php';

$stdout = new StreamHandler(STDOUT, Level::Debug);
$file = new StreamHandler(__DIR__ . '/log_' . getmypid() . '.log', Level::Debug);

return new Logger('cluster', [$stdout, $file]);