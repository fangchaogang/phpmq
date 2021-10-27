<?php

use phpmq\drivers\beanstalk\Queue;

require dirname(__FILE__) . '/../vendor/autoload.php';

//use phpmq\drivers\redis\Queue;
//
//$queue = new Queue([
//    'host' => '127.0.0.1',
//    'port' => 6379
//]);
//$queue = new \phpmq\drivers\amqp_interop\Queue([
//    "host"=>"127.0.0.1",
//    "port"=>5672,
//    "user" => "root",
//    "password" => "root",
//    "vhost"=>"/"
//]);

$queue = new Queue([
    "host"=>"150.158.185.89",
]);
$queue->listen();