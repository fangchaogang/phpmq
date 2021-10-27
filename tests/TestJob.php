<?php
namespace phpmq\tests;

use phpmq\Job;
use phpmq\Queue;

class TestJob extends Job
{
    public $data = [];

    public function canRetry($attempt, \Throwable $error)
    {
        if ($attempt >= 2) {
            return false;
        }
        return  true;
    }

    public function execute(Queue $queue)
    {
        var_dump($this->data);
        throw new \Exception('ssss');
    }
}