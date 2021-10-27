<?php
namespace phpmq\tests;

use phpmq\drivers\beanstalk\Queue;

class BeanstalkTest extends TestCase
{
    public function testMq()
    {
        $queue = new Queue([
            "host"=>"150.158.185.89",
        ]);
        $job = new TestJob();
        $job->data = ['delay' => 1];
        $queue->delay(1)->ttr(5)->push($job);
    }
}