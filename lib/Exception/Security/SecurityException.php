<?php

namespace Lib\Exception\Security;

/**
 * Class SecurityException
 * @package Lib\Security
 */
class SecurityException extends \Exception
{
    /**
     * SecurityException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}