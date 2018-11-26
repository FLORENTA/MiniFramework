<?php

namespace Lib\Throwable\Routing;

/**
 * Class RoutingException
 * @package Lib\Throwable\Routing
 */
class RoutingException extends \Exception
{
    /**
     * RoutingException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}