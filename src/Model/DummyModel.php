<?php

namespace Model;

use Classes\Model\Model;

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