<?php

namespace Lib\Exception\Routing;

/**
 * Class NoRouteFoundException
 * @package Lib\Routing
 */
class NoRouteFoundException extends \Exception
{
    /**
     * NoRouteFoundException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}