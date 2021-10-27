<?php
namespace phpmq;

abstract class Job extends BaseObject implements JobInterface
{
    /**
     * @var int default time to reserve a job
     */
    public $ttr = 300;
    /**
     * @var int default attempt count
     */
    public $attempts = 1;

    public function getTtr()
    {
        return $this->ttr;
    }


}