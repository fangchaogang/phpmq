<?php
namespace phpmq\serializers;

use phpmq\BaseObject;

/**
 * Php Serializer.
 *
 */
class PhpSerializer extends BaseObject implements SerializerInterface
{
    /**
     * @inheritdoc
     */
    public function serialize($job)
    {
        return serialize($job);
    }

    /**
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        return unserialize($serialized);
    }
}
