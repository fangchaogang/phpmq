<?php
namespace phpmq\serializers;

use phpmq\JobInterface;

/**
 * Serializer Interface.
 */
interface SerializerInterface
{
    /**
     * @param JobInterface|mixed $job
     * @return string
     */
    public function serialize($job);

    /**
     * @param string $serialized
     * @return JobInterface
     */
    public function unserialize($serialized);
}
