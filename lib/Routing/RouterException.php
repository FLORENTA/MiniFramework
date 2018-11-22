<?php

namespace Lib\Routing;

/**
 * Class RouterException
 * @package Lib\Routing
 */
class RouterException extends \Exception
{
    /**
     * RouterException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}