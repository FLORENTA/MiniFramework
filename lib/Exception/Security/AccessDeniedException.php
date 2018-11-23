<?php

namespace Lib\Exception\Security;

/**
 * Class AccessDeniedException
 * @package Lib\Exception\Security
 */
class AccessDeniedException extends \Exception
{
    /**
     * AccessDeniedException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}