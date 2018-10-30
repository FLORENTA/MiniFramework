<?php

namespace Classes\Model\Orm;

use Classes\Model\Relation\RelationType;
use Classes\Utils\Tools;

/**
 * Class SchemaGenerator
 * @package Classes\Model\Orm
 */
class SchemaGenerator
{
    /** @var EntityManager $em */
    private $em;

    /**
     * SchemaGenerator constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->em = $entityManager;
    }

    public function createSchema()
    {
        $loadedClassMetaData =
            $this->em
            ->getClassMetaDataFactory()
            ->getLoadedClassMetaData();

        $qb = new QueryBuilder($this->em);

        /** @var ClassMetaData $classMetaData */
        foreach ($loadedClassMetaData as $classMetaData) {

            $table     = $classMetaData->table;
            $fields    = $classMetaData->fields;
            $relations = $classMetaData->relations;
            $primary   = null;

            if ($table) {

                $qb->createTable($table);

                /** @var string|null $primaryKey the table primary key */
                $primaryKey = $classMetaData->getPrimaryKey();

                /**
                 * @var string $field
                 * @var array $value
                 */
                foreach ($fields as $field => $value) {

                    /** @var string|null $columnName */
                    $columnName = $classMetaData->getColumnName($value);

                    $columnName = $columnName ?: Tools::splitCamelCasedWords($field);

                    /** @var string|null $type */
                    $type    = $classMetaData->getType($value);

                    /** @var string|int|null $length */
                    $length  = $classMetaData->getLength($value);

                    /** @var bool $null */
                    $null = $classMetaData->isNullable($value);

                    $qb->addColumn($columnName);

                    if (!is_null($type)) {
                        $qb->addType($type);
                    } else {
                        throw new \Exception(
                            sprintf('Undefined type for field %s', $field)
                        );
                    }

                    if (!is_null($length)) {
                        $qb->addLength($length);
                    }

                    $qb->addNull($null);
                }

                if (!is_null($primaryKey)) {
                    $qb->addPrimaryKey($primaryKey);
                }

                // Iterating over many to one relations
                foreach ($relations as $relation => $data) {
                    if ($relation === RelationType::MANY_TO_ONE) {
                        foreach ($data as $attribute => $args) {
                            /** @var null|string $joinColumn */
                            $joinColumn = $classMetaData->getJoinColumn($args);

                            /** @var ClassMetaData $targetClassMetaData */
                            $targetClassMetaData = $loadedClassMetaData[$args['target']];
                            $targetClassTable = $targetClassMetaData->table;
                            $targetClassPrimaryKey = $targetClassMetaData->getPrimaryKey();

                            // As not defined in file, let's create a join column
                            // corresponding to the target class table to which '_id'
                            // is added
                            if (is_null($joinColumn)) {
                                $joinColumn = $targetClassTable . '_id';
                            }

                            $qb->addJoinColumn($joinColumn);

                            $qb->addForeignKey(
                                $joinColumn,
                                $targetClassTable,
                                $targetClassPrimaryKey
                            );
                        }
                    }

                    // Iterating over many to many relations
                    if ($relation === RelationType::MANY_TO_MANY) {
                        foreach ($data as $attribute => $args) {

                            /** @var null|string $joinColumn */
                            $joinTable = $classMetaData->getJoinTable($args);

                            /** @var ClassMetaData $targetClassMetaData */
                            $targetClassMetaData = $loadedClassMetaData[$args['target']];
                            $targetClassTable = $targetClassMetaData->table;
                            $targetClassPrimaryKey = $targetClassMetaData->getPrimaryKey();

                            // As not defined in file, let's create a join column
                            // corresponding to the target class table to which '_id'
                            // is added
                            if (is_null($joinTable) && $classMetaData->isOwningSide($args)) {
                                $joinTable = $targetClassTable . '_id';
                            }
                        }
                    }
                }

                $qb->removeComma()->addEndDelimiter();

                $qb->getQuery()->execute();
            }
        }
    }
}