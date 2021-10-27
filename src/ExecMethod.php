<?php
namespace phpmq;

class ExecMethod extends Method
{
    /**
     * 尝试次数
     */
    public $attempt;
    /**
     * 执行结果
     */
    public $result;
    /**
     * @var null|\Exception|\Throwable
     */
    public $error;
    /**
     * 重试
     * @var null|bool
     */
    public $retry;
}