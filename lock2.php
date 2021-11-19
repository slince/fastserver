<?php


$file = __DIR__ . '/a.lock';

$res = fopen($file, 'a+');

if (flock($res, LOCK_EX|LOCK_NB)) {
    var_dump('上锁成功');
} else {
    var_dump('上锁是败笔');
}

fwrite($res, "hello");

sleep(600);