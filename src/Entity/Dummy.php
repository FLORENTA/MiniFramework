<?php

namespace Entity;

/**
 * Class Dummy
 * @package Entity
 */
class Dummy
{
    /** @var integer $number */
    private $number;

    /**
     * @param integer $number
     */
    public function setNumber($number)
    {
        $this->number = $number;
    }

    /**
     * @return integer
     */
    public function getNumber()
    {
        return $this->number;
    }
}