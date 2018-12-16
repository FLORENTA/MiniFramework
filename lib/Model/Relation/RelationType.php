<?php

namespace Lib\Model\Relation;

/**
 * Class RelationType
 * @package Lib
 */
class RelationType
{
    const ONE_TO_ONE   = 'oneToOne';
    const ONE_TO_MANY  = 'oneToMany';
    const MANY_TO_ONE  = 'manyToOne';
    const MANY_TO_MANY = 'manyToMany';

    /**
     * @return array
     */
    public static function getRelationTypes()
    {
        return [
            self::ONE_TO_ONE,
            self::ONE_TO_MANY,
            self::MANY_TO_ONE,
            self::MANY_TO_MANY
        ];
    }
}