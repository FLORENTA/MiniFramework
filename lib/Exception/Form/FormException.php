<?php

namespace Lib\Exception\Form;

/**
 * Class FormException
 * @package Lib\Exception\Form
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