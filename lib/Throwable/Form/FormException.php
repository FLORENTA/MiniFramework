<?php

namespace Lib\Throwable\Form;

/**
 * Class FormException
 * @package Lib\Throwable\Form
 */
class FormException extends \Exception
{
    /**
     * FormException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}