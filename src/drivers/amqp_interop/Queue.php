<?php
namespace phpmq\drivers\amqp_interop;

use phpmq\Queue as BaseQueue;
use Enqueue\AmqpLib\AmqpConnectionFactory as AmqpLibConnectionFactory;
use Enqueue\AmqpTools\DelayStrategyAware;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;

class Queue extends BaseQueue
{
    const ATTEMPT = 'mq-attempt';
    const TTR = 'mq-ttr';
    const DELAY = 'mq-delay';
    const PRIORITY = 'mq-priority';

    /**
     * The connection to the borker could be configured as an array of options
     * or as a DSN string like amqp:, amqps:, amqps://user:pass@localhost:1000/vhost.
     *
     * @var string
     */
    public $dsn;
    /**
     * The message queue broker's host.
     *
     * @var string|null
     */
    public $host;
    /**
     * The message queue broker's port.
     *
     * @var string|null
     */
    public $port;
    /**
     * This is RabbitMQ user which is used to login on the broker.
     *
     * @var string|null
     */
    public $user;
    /**
     * This is RabbitMQ password which is used to login on the broker.
     *
     * @var string|null
     */
    public $password;
    /**
     * Virtual hosts provide logical grouping and separation of resources.
     *
     * @var string|null
     */
    public $vhost;
    /**
     * The time PHP socket waits for an information while reading. In seconds.
     *
     * @var float|null
     */
    public $readTimeout;
    /**
     * The time PHP socket waits for an information while witting. In seconds.
     *
     * @var float|null
     */
    public $writeTimeout;
    /**
     * The time RabbitMQ keeps the connection on idle. In seconds.
     *
     * @var float|null
     */
    public $connectionTimeout;
    /**
     * The periods of time PHP pings the broker in order to prolong the connection timeout. In seconds.
     *
     * @var float|null
     */
    public $heartbeat;
    /**
     * PHP uses one shared connection if set true.
     *
     * @var bool|null
     */
    public $persisted;
    /**
     * The connection will be established as later as possible if set true.
     *
     * @var bool|null
     */
    public $lazy;
    /**
     * If false prefetch_count option applied separately to each new consumer on the channel
     * If true prefetch_count option shared across all consumers on the channel.
     *
     * @var bool|null
     */
    public $qosGlobal;
    /**
     * Defines number of message pre-fetched in advance on a channel basis.
     *
     * @var int|null
     */
    public $qosPrefetchSize;
    /**
     * Defines number of message pre-fetched in advance per consumer.
     *
     * @var int|null
     */
    public $qosPrefetchCount;
    /**
     * Defines whether secure connection should be used or not.
     *
     * @var bool|null
     */
    public $sslOn;
    /**
     * Require verification of SSL certificate used.
     *
     * @var bool|null
     */
    public $sslVerify;
    /**
     * Location of Certificate Authority file on local filesystem which should be used with the verify_peer context option to authenticate the identity of the remote peer.
     *
     * @var string|null
     */
    public $sslCacert;
    /**
     * Path to local certificate file on filesystem.
     *
     * @var string|null
     */
    public $sslCert;
    /**
     * Path to local private key file on filesystem in case of separate files for certificate (local_cert) and private key.
     *
     * @var string|null
     */
    public $sslKey;
    /**
     * The queue used to consume messages from.
     *
     * @var string
     */
    public $queueName = 'interop_queue';
    /**
     * The exchange used to publish messages to.
     *
     * @var string
     */
    public $exchangeName = 'exchange';

    /**
     * This property should be an integer indicating the maximum priority the queue should support. Default is 10.
     *
     * @var int
     */
    public $maxPriority = 10;

    /**
     * Amqp interop context.
     *
     * @var AmqpContext
     */
    protected $context;

    /**
     * The property tells whether the setupBroker method was called or not.
     * Having it we can do broker setup only once per process.
     *
     * @var bool
     */
    protected $setupBrokerDone = false;

    /**
     * Listens amqp-queue and runs new jobs.
     */
    public function listen()
    {
        $this->open();
        $this->setupBroker();

        $queue = $this->context->createQueue($this->queueName);
        $consumer = $this->context->createConsumer($queue);

        $callback = function (AmqpMessage $message, AmqpConsumer $consumer) {
            if ($message->isRedelivered()) {
                $consumer->acknowledge($message);

                $this->redeliver($message);

                return true;
            }

            $ttr = $message->getProperty(self::TTR);
            $attempt = $message->getProperty(self::ATTEMPT, 1);

            if ($this->handleMessage($message->getMessageId(), $message->getBody(), $ttr, $attempt, $message)) {
                $consumer->acknowledge($message);
            } else {
                $consumer->acknowledge($message);
                $this->redeliver($message);
            }

            return true;
        };

        $subscriptionConsumer = $this->context->createSubscriptionConsumer();
        $subscriptionConsumer->subscribe($consumer, $callback);
        $subscriptionConsumer->consume();
    }

    /**
     * @var array
     */
    protected $callbacks = [];

    /**
     * 注册routingKey处理方法
     * @param $routingKey
     * @param callable $callback
     * @return $this
     */
    public function regRoutingKeyCallback($routingKey, callable $callback)
    {
        $this->callbacks[$routingKey] = $callback;
        return $this;
    }

    /**
     * @var string
     */
    protected $routingKey;

    /**
     * 设置routingKey
     * @param $routingKey
     * @return $this
     */
    public function setRoutingKey($routingKey)
    {
        $this->routingKey = $routingKey;
        return $this;
    }

    /**
     * @param $id
     * @param $message
     * @param $ttr
     * @param $attempt
     * @param AmqpMessage $extMessage
     * @return bool|null
     */
    protected function handleMessage($id, $message, $ttr, $attempt, $extMessage = null)
    {
        $result = parent::handleMessage($id, $message, $ttr, $attempt);
        if ($result === null) {
            if (isset($this->callbacks[$extMessage->getRoutingKey()])) {
                return call_user_func($this->callbacks[$extMessage->getRoutingKey()], $message);
            }
            return true;
        }
        return $result;
    }
    /**
     * @return AmqpContext
     */
    public function getContext()
    {
        $this->open();

        return $this->context;
    }

    /**
     * @inheritdoc
     */
    protected function pushMessage($payload, $ttr, $delay, $priority)
    {
        $this->open();
        $this->setupBroker();

        $topic = $this->context->createTopic($this->exchangeName);

        $message = $this->context->createMessage($payload);
        $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);
        $message->setMessageId(uniqid('', true));
        $message->setTimestamp(time());
        $message->setProperty(self::ATTEMPT, 1);
        $message->setProperty(self::TTR, $ttr);

        $producer = $this->context->createProducer();

        if ($delay) {
            $message->setProperty(self::DELAY, $delay);
            $producer->setDeliveryDelay($delay * 1000);
        }

        if ($priority) {
            $message->setProperty(self::PRIORITY, $priority);
            $producer->setPriority($priority);
        }
        $this->routingKey && $message->setRoutingKey($this->routingKey);
        $producer->send($topic, $message);

        return $message->getMessageId();
    }

    protected function open()
    {
        if ($this->context) {
            return;
        }
        $config = [
            'dsn' => $this->dsn,
            'host' => $this->host,
            'port' => $this->port,
            'user' => $this->user,
            'pass' => $this->password,
            'vhost' => $this->vhost,
            'read_timeout' => $this->readTimeout,
            'write_timeout' => $this->writeTimeout,
            'connection_timeout' => $this->connectionTimeout,
            'heartbeat' => $this->heartbeat,
            'persisted' => $this->persisted,
            'lazy' => $this->lazy,
            'qos_global' => $this->qosGlobal,
            'qos_prefetch_size' => $this->qosPrefetchSize,
            'qos_prefetch_count' => $this->qosPrefetchCount,
            'ssl_on' => $this->sslOn,
            'ssl_verify' => $this->sslVerify,
            'ssl_cacert' => $this->sslCacert,
            'ssl_cert' => $this->sslCert,
            'ssl_key' => $this->sslKey,
        ];
        $config = array_filter($config, function ($value) {
            return null !== $value;
        });
        $factory = new AmqpLibConnectionFactory($config);
        $this->context = $factory->createContext();

        if ($this->context instanceof DelayStrategyAware) {
            $this->context->setDelayStrategy(new RabbitMqDlxDelayStrategy());
        }
    }

    protected function setupBroker()
    {
        if ($this->setupBrokerDone) {
            return;
        }

        $queue = $this->context->createQueue($this->queueName);
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        $queue->setArguments(['x-max-priority' => $this->maxPriority]);
        $this->context->declareQueue($queue);

        $topic = $this->context->createTopic($this->exchangeName);
        $topic->setType(AmqpTopic::TYPE_DIRECT);
        $topic->addFlag(AmqpTopic::FLAG_DURABLE);
        $this->context->declareTopic($topic);

        $this->context->bind(new AmqpBind($queue, $topic));

        $this->setupBrokerDone = true;
    }

    /**
     * Closes connection and channel.
     */
    protected function close()
    {
        if (!$this->context) {
            return;
        }

        $this->context->close();
        $this->context = null;
        $this->setupBrokerDone = false;
    }

    /**
     * {@inheritdoc}
     */
    protected function redeliver(AmqpMessage $message)
    {
        $attempt = $message->getProperty(self::ATTEMPT, 1);
        $ttr = $message->getProperty(self::TTR, 0);

        $newMessage = $this->context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $newMessage->setDeliveryMode($message->getDeliveryMode());
        $newMessage->setProperty(self::ATTEMPT, ++$attempt);
        $producer = $this->context->createProducer();
        if ($ttr) {
            $producer->setDeliveryDelay($ttr * 1000);
        }

        $producer->send(
            $this->context->createQueue($this->queueName),
            $newMessage
        );
    }

}