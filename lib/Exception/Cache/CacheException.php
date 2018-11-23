<?php

namespace Lib\Exception\Cache;

/**
 * Class CacheException
 * @package Lib\Utils
 */
class CacheException extends \Exception
{
    /**
     * CacheException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}