<?php

namespace Lib\Routing;

/**
 * Class ControllerNotFoundException
 * @package Lib\Routing
 */
class ControllerNotFoundException extends \Exception
{
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}