# phpmq
PHP队列集合（rbbitmq、redis、）

### 安装

```shell
composer require fangchaogang/phpmq "v1.*"
```
#### 使用rabbitmq

```php
use phpmq\drivers\amqp_interop\Queue;
config = [
  "host"=>"127.0.0.1",
  "port"=>5672,
  "user" => "root",
  "password" => "root",
  "vhost"=>"/"
];
$queue = new Queue($config);
//发消息
$job = new TestJob();
$job->data = ['delay' => '5',];
//直接发
$queue->push($job);
//延时发
$queue->delay(1)->push($job);
//带routingKey发
$queue->setRoutingKey('modify')->push($job);
//其他参考源码
```

#### 使用redis

```php
use phpmq\drivers\redis\Queue;

```
