<?php


$file = __DIR__ . '/a.lock';

$res = fopen($file, 'a+');

flock($res, LOCK_SH);

fwrite($res, "hello");

sleep(600);