<?php

namespace Lib\Security;

/**
 * Class SecurityException
 * @package Lib\Security
 */
class SecurityException extends \Exception
{
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}