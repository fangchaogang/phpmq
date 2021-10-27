<?php
namespace phpmq\serializers;

use phpmq\BaseObject;
use phpmq\InvalidArgumentException;

/**
 * Json Serializer.
 *
 */
class JsonSerializer extends BaseObject implements SerializerInterface
{
    /**
     * @var string
     */
    public $classKey = 'class';
    /**
     * @var int
     */
    public $options = 0;


    /**
     * @inheritdoc
     */
    public function serialize($job)
    {
        return json_encode($this->toArray($job), $this->options);
    }

    /**
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        return $this->fromArray(json_decode($serialized, true));
    }

    /**
     * @param $data
     * @return array
     * @throws InvalidArgumentException
     */
    protected function toArray($data)
    {
        if (is_object($data)) {
            $result = [$this->classKey => get_class($data)];
            foreach (get_object_vars($data) as $property => $value) {
                if ($property === $this->classKey) {
                    throw new InvalidArgumentException("Object cannot contain $this->classKey property.");
                }
                $result[$property] = $this->toArray($value);
            }

            return $result;
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                if ($key === $this->classKey) {
                    throw new InvalidArgumentException("Array cannot contain $this->classKey key.");
                }
                $result[$key] = $this->toArray($value);
            }
            return $result;
        }

        return $data;
    }

    /**
     * @param $data
     * @return array|mixed
     */
    protected function fromArray($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        if (!isset($data[$this->classKey])) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->fromArray($value);
            }
            return $result;
        }
        $config = [];
        $class = $data[$this->classKey];
        unset($data[$this->classKey]);
        foreach ($data as $property => $value) {
            $config[$property] = $this->fromArray($value);
        }
        return new $class($config);
    }

}
