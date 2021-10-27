<?php
namespace phpmq;

use phpmq\serializers\PhpSerializer;
use phpmq\serializers\SerializerInterface;

/**
 * Class Queue
 * @package phpmq
 */
abstract class Queue extends BaseObject
{

    public $ttr = 300;
    /**
     * @var int default attempt count
     */
    public $attempts = 1;

    /**
     * @var SerializerInterface|array
     */
    public $serializer = PhpSerializer::class;

    private $pushTtr;
    private $pushDelay;
    private $pushPriority;

    /**
     * @throws InvalidArgumentException
     */
    public function init()
    {
        parent::init();
        $this->serializer = new $this->serializer();
        if (!is_numeric($this->ttr)) {
            throw new InvalidArgumentException('Default TTR must be integer.');
        }
        $this->ttr = (int) $this->ttr;
        if ($this->ttr <= 0) {
            throw new InvalidArgumentException('Default TTR must be greater that zero.');
        }

        if (!is_numeric($this->attempts)) {
            throw new InvalidArgumentException('Default attempts count must be integer.');
        }
        $this->attempts = (int) $this->attempts;
        if ($this->attempts <= 0) {
            throw new InvalidArgumentException('Default attempts count must be greater that zero.');
        }
    }
    /**
     * Sets TTR for job execute.
     *
     * @param int|mixed $value
     * @return $this
     */
    public function ttr($value)
    {
        $this->pushTtr = $value;
        return $this;
    }

    /**
     * Sets delay for later execute.
     *
     * @param int|mixed $value
     * @return $this
     */
    public function delay($value)
    {
        $this->pushDelay = $value;
        return $this;
    }

    /**
     * Sets job priority.
     *
     * @param mixed $value
     * @return $this
     */
    public function priority($value)
    {
        $this->pushPriority = $value;
        return $this;
    }

    /**
     * Sets job serialize.
     * @param $value
     * @return $this
     */
    public function serialize($value)
    {
        $this->serializer = new $value();
        return $this;
    }

    /**
     * @param $job
     * @return string|null
     * @throws InvalidArgumentException
     */
    public function push($job)
    {
        $method = new PushMethod([
            'job' => $job,
            'ttr' => $this->pushTtr ?: (
            $job instanceof JobInterface
                ? $job->getTtr()
                : $this->ttr
            ),
            'delay' => $this->pushDelay ?: 0,
            'priority' => $this->pushPriority,
        ]);
        $this->pushTtr = null;
        $this->pushDelay = null;
        $this->pushPriority = null;

        if (!($method->job instanceof JobInterface)) {
            throw new InvalidArgumentException('Job must be instance of JobInterface.');
        }

        if (!is_numeric($method->ttr)) {
            throw new InvalidArgumentException('Job TTR must be integer.');
        }
        $method->ttr = (int) $method->ttr;
        if ($method->ttr <= 0) {
            throw new InvalidArgumentException('Job TTR must be greater that zero.');
        }

        if (!is_numeric($method->delay)) {
            throw new InvalidArgumentException('Job delay must be integer.');
        }
        $method->delay = (int) $method->delay;
        if ($method->delay < 0) {
            throw new InvalidArgumentException('Job delay must be positive.');
        }
        $message = $this->serializer->serialize($method->job);
        $method->id = $this->pushMessage($message, $method->ttr, $method->delay, $method->priority);

        return $method->id;
    }

    /**
     * @param string $message
     * @param int $ttr time to reserve in seconds
     * @param int $delay
     * @param mixed $priority
     * @return string id of a job message
     */
    abstract protected function pushMessage($message, $ttr, $delay, $priority);

    protected function handleMessage($id, $message, $ttr, $attempt)
    {
        /** @var  Job $job */
        list($job, $error) = $this->unserializeMessage($message);
        $method = new ExecMethod([
            'id' => $id,
            'job' => $job,
            'ttr' => $ttr,
            'attempt' => $attempt,
            'error' => $error,
        ]);

        if ($method->error) {
            return $this->handleError($method);
        }
        try {
            $method->result = $method->job->execute($this);
        } catch (\Exception $error) {
            $method->error = $error;
            return $this->handleError($method);
        } catch (\Throwable $error) {
            $method->error = $error;
            return $this->handleError($method);
        }
        return true;
    }

    /**
     * @param ExecMethod $method
     * @return bool
     * @internal
     */
    public function handleError(ExecMethod $method)
    {
        $method->retry = $method->attempt < $this->attempts;
        if ($method->error instanceof InvalidJobException) {
            return null;
        } elseif ($method->job instanceof JobInterface) {
            $method->retry = $method->job->canRetry($method->attempt, $method->error);
        }
        return !$method->retry;
    }

    /**
     * @param $serialized
     * @return array
     */
    public function unserializeMessage($serialized)
    {
        try {
            $job = $this->serializer->unserialize($serialized);
        } catch (\Exception $e) {
            return [null, new InvalidJobException($e->getMessage(), -1)];
        }
        if ($job instanceof JobInterface) {
            return [$job, null];
        }

        return [null, new InvalidJobException(sprintf(
            'Job must be a JobInterface instance instead of %s.', 'JobInterface'
        ))];
    }
}