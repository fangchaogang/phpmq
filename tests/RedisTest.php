<?php
namespace phpmq\tests;

use phpmq\drivers\redis\Queue;

class RedisTest extends TestCase
{
    public function testMq()
    {
        $queue = new Queue([
           'host' => '127.0.0.1', 'port' => 6379
        ]);
        $job = new TestJob();
        $job->data = ['delay' => 1];
        $queue->delay(1)->ttr(5)->push($job);
    }
}