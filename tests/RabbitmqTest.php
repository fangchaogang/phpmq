<?php
namespace phpmq\tests;

use phpmq\drivers\amqp_interop\Queue;

class RabbitmqTest extends TestCase
{
    public function testMq()
    {
        $queue = new Queue([
            "host"=>"127.0.0.1",
            "port"=>5672,
            "user" => "root",
            "password" => "root",
            "vhost"=>"/"
        ]);
        $job = new TestJob();
        $job->data = ['delay' => 1];
        $queue->delay(1)->ttr(5)->push($job);
    }
}