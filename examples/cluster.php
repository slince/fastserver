<?php

use Waveman\Cluster\Cluster;

include __DIR__ . '/../vendor/autoload.php';

$cluster = Cluster::get();

if ($cluster->isPrimary) {

    $worker = $cluster->fork();

    $worker->on('message', function ($message){

    });

    $worker->send()l
} else {

}