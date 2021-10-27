<?php
namespace phpmq;

class PushMethod extends Method
{
    /**
     * @var int
     */
    public $delay;
    /**
     * @var mixed
     */
    public $priority;
}