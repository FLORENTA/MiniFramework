<?php

namespace Lib\Throwable;

/**
 * Class QueryBuilderException
 * @package Lib\Throwable
 */
class QueryBuilderException extends \Exception
{
    /**
     * QueryBuilderException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}