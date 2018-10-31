<?php

namespace Classes\Model\Orm;

use Classes\Model\Relation\RelationType;

/**
 * Class EntityGenerator
 * @package Classes\Model\Orm
 */
class EntityGenerator
{
    /** @var ClassMetaDataFactory $classMetaDataFactory */
    private $classMetaDataFactory;

    /**
     * EntityGenerator constructor.
     * @param ClassMetaDataFactory $classMetaDataFactory
     */
    public function __construct(ClassMetaDataFactory $classMetaDataFactory)
    {
        $this->classMetaDataFactory = $classMetaDataFactory;
    }

    /**
     * @param string $entity
     */
    public function generateFile($entity)
    {
        $entity = "Entity\\$entity";

        /** @var ClassMetaData $classMetaData */
        $classMetaData = $this->classMetaDataFactory->getClassMetaData($entity);

        /** @var array $fields */
        $fields = $classMetaData->fields;

        /** @var array $manyToOneRelations */
        $manyToOneRelations = $classMetaData->getRelations(
            RelationType::MANY_TO_ONE
        );

    }
}