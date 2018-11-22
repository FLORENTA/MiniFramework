<?php

namespace Lib\Utils;

/**
 * Class CacheException
 * @package Lib\Utils
 */
class CacheException extends \Exception
{
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}