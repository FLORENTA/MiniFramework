<?php

namespace Lib\Exception\Controller;

/**
 * Class ControllerNotFoundException
 * @package Lib\Routing
 */
class ControllerNotFoundException extends \Exception
{
    /**
     * ControllerNotFoundException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}