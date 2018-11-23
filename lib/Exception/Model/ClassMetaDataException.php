<?php

namespace Lib\Model\Exception\Model;

/**
 * Class ClassMetaDataException
 * @package Lib\Model\Exception
 */
class ClassMetaDataException extends \Exception
{
    /**
     * ClassMetaDataException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }
}