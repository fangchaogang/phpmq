<?php
namespace phpmq;

abstract class Method extends BaseObject
{
    /**
     * @var Queue
     */
    public $queue;
    /**
     * @var string|null unique id of a job
     */
    public $id;
    /**
     * @var JobInterface|null
     */
    public $job;
    /**
     * @var int time to reserve in seconds of the job
     */
    public $ttr;
}