<?php

namespace Lib\Model\Exception;

/**
 * Class ClassMetaDataException
 * @package Lib\Model\Exception
 */
class ClassMetaDataException extends \Exception
{
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}