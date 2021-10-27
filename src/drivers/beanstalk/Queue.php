<?php
namespace phpmq\drivers\beanstalk;

use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Pheanstalk;
use phpmq\Queue as BaseQueue;

class Queue  extends BaseQueue
{
    /**
     * @var string connection host
     */
    public $host = 'localhost';
    /**
     * @var int connection port
     */
    public $port = PheanstalkInterface::DEFAULT_PORT;
    /**
     * @var string beanstalk tube
     */
    public $queueName = 'queue';

    private $tube;

    public function init()
    {
        parent::init();
        $this->tube = $this->queueName;
    }

    /**
     * @var int
     */
    public $timeout = 1;

    public function listen()
    {
        while (true) {
            try {
                if ($payload = $this->getPheanstalk()->watchOnly($this->tube)->reserveWithTimeout($this->timeout)) {
                    $info = $this->getPheanstalk()->statsJob($payload);
                    if ($this->handleMessage($payload->getId(), $payload->getData(), $info['ttr'], $info['reserves'])) {
                        $this->getPheanstalk()->delete($payload);
                    }
                }
            } catch (\Pheanstalk\Exception\DeadlineSoonException $e) {
                //不处理
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function pushMessage($message, $ttr, $delay, $priority)
    {
        return $this->getPheanstalk()->useTube($this->tube)->put(
            $message,
            $priority ?: PheanstalkInterface::DEFAULT_PRIORITY,
            $delay,
            $ttr
        );
    }

    /**
     * @return Pheanstalk
     */
    protected function getPheanstalk()
    {
        if (!$this->pheanstalk) {
            $this->pheanstalk = Pheanstalk::create($this->host, $this->port);
        }
        return $this->pheanstalk;
    }

    /**
     * @var Pheanstalk
     */
    private $pheanstalk;
}