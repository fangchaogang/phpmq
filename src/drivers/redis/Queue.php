<?php
namespace phpmq\drivers\redis;

use phpmq\Queue as BaseQueue;
use Exception;

class Queue extends BaseQueue
{
    /**
     * @var string
     */
    public $host = 'localhost';
    /**
     * @var string
     */
    public $password;
    /**
     * @var int
     */
    public $port = 6379;
    /**
     * @var int
     */
    public $database = 0;
    /**
     * @var int
     */
    public $timeout = 3;
    /**
     * @var Connection|array|string
     */
    public $redis = 'redis';
    /**
     * @var string
     */
    public $channel = 'queue';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->redis = new Connection([
            'hostname' => $this->host,
            'password' => $this->password,
            'port' => $this->port,
            'database' => $this->database
        ]);
    }

    public function listen()
    {
        while (true) {
            if (($payload = $this->reserve($this->timeout)) !== null) {
                list($id, $message, $ttr, $attempt) = $payload;
                if ($this->handleMessage($id, $message, $ttr, $attempt)) {
                    $this->delete($id);
                }
            }
        }
    }

    /**
     * @param int $timeout timeout
     * @return array|null payload
     */
    protected function reserve($timeout)
    {
        // Moves delayed and reserved jobs into waiting list with lock for one second
        if ($this->redis->set("$this->channel.moving_lock", true, 'NX', 'EX', 1)) {
            $this->moveExpired("$this->channel.delayed");
            $this->moveExpired("$this->channel.reserved");
        }

        // Find a new waiting message
        $id = null;
        if (!$timeout) {
            $id = $this->redis->rpop("$this->channel.waiting");
        } elseif ($result = $this->redis->brpop("$this->channel.waiting", $timeout)) {
            $id = $result[1];
        }
        if (!$id) {
            return null;
        }

        $payload = $this->redis->hget("$this->channel.messages", $id);
        list($ttr, $message) = explode(';', $payload, 2);
        $this->redis->zadd("$this->channel.reserved", time() + $ttr, $id);
        $attempt = $this->redis->hincrby("$this->channel.attempts", $id, 1);

        return [$id, $message, $ttr, $attempt];
    }

    /**
     * @param string $from
     */
    protected function moveExpired($from)
    {
        $now = time();
        if ($expired = $this->redis->zrevrangebyscore($from, $now, '-inf')) {
            $this->redis->zremrangebyscore($from, '-inf', $now);
            foreach ($expired as $id) {
                $this->redis->rpush("$this->channel.waiting", $id);
            }
        }
    }

    /**
     * Deletes message by ID.
     *
     * @param int $id of a message
     */
    protected function delete($id)
    {
        $this->redis->zrem("$this->channel.reserved", $id);
        $this->redis->hdel("$this->channel.attempts", $id);
        $this->redis->hdel("$this->channel.messages", $id);
    }

    /**
     * @param string $message
     * @param int $ttr
     * @param int $delay
     * @param mixed $priority
     * @return mixed|string
     * @throws Exception
     */
    protected function pushMessage($message, $ttr, $delay, $priority)
    {
        if ($priority !== null) {
            throw new Exception('Job priority is not supported in the driver.');
        }

        $id = $this->redis->incr("$this->channel.message_id");
        $this->redis->hset("$this->channel.messages", $id, "$ttr;$message");
        if (!$delay) {
            $this->redis->lpush("$this->channel.waiting", $id);
        } else {
            $this->redis->zadd("$this->channel.delayed", time() + $delay, $id);
        }

        return $id;
    }

}