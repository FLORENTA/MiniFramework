<?php

namespace Model;

use Lib\Model\Model;

/**
 * Class DummyModel
 * @package Model
 */
class DummyModel extends Model
{
    public function test()
    {
        $this->em->createQueryBuilder();
    }
}