<?php

namespace Lib\Throwable\Response;

/**
 * Class RenderException
 * @package Lib\Throwable\Response
 */
class RenderException extends \Exception
{
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}