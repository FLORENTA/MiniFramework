<?php

namespace Lib\Routing;

/**
 * Class NoRouteFoundException
 * @package Lib\Routing
 */
class NoRouteFoundException extends \Exception
{
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}