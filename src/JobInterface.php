<?php
namespace phpmq;

/**
 * Job Interface.
 *
 */
interface JobInterface
{

    /**
     * @return int time to reserve in seconds
     */
    public function getTtr();

    /**
     * @param int $attempt number
     * @param \Exception|\Throwable $error from last execute of the job
     * @return bool
     */
    public function canRetry($attempt, \Throwable $error);

    /**
     * @param Queue $queue which pushed and is handling the job
     * @return void|mixed result of the job execution
     */
    public function execute(Queue $queue);
}
