<?php

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

include __DIR__ . '/../vendor/autoload.php';

$handler = new StreamHandler(STDOUT, Level::Debug);

return new Logger('cluster', [$handler]);