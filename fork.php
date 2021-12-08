<?php


class A{
    public $a = 123;
}

$a = new A();
$b = $a;

$b = new A();
$b->a = 456;

var_dump($a->a, $b->a);

exit;
define('FORK_NUMS', 5);
$pids = array();

//Create 5 sub-processes
for($i = 0; $i < FORK_NUMS; ++$i) {
    $pids[$i] = pcntl_fork();
    if($pids[$i] == -1) {
        die('fork error');
    } else if ($pids[$i]) {
        pcntl_wait($status);
    } else {
        echo getmypid() , " {$i} \r\n";
        exit;
    }
}