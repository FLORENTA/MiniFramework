<?php

namespace Classes\Model\Orm;

use Classes\Model\Relation\RelationType;

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
        $loadedClassMetaData = $this->em->getClassMetaDataFactory()->getLoadedClassMetaData();

        $qb = new QueryBuilder($this->em);

        /** @var ClassMetaData $classMetaData */
        foreach ($loadedClassMetaData as $classMetaData) {

            $table     = $classMetaData->table;
            $fields    = $classMetaData->fields;
            $relations = $classMetaData->relations;
            $primary   = null;

            if ($table) {

                /* Init table creation */
                $qb->createTable($table);

                /** @var string|null $primaryKey the table primary key */
                $primaryKey = $classMetaData->getPrimaryKey();

                /**
                 * @var string $field
                 * @var array $value
                 */
                foreach ($fields as $field => $value) {

                    /** @var string|null $columnName */
                    $columnName = $classMetaData->getColumnName($value, $field);

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

                    // Add autoincrement if primary key looped
                    if ($field === $primaryKey) {
                        $qb->addAutoIncrement();
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

                        $qb->removeComma()->addEndDelimiter();
                    }

                    // Iterating over many to many relations
                    if ($relation === RelationType::MANY_TO_MANY) {
                        foreach ($data as $attribute => $args) {

                            // Checking owning side only
                            if ($classMetaData->isOwningSide($args)) {

                                /** @var null|string $joinColumn */
                                $joinTable = $classMetaData->getJoinTable($args);

                                /** @var ClassMetaData $targetClassMetaData */
                                $targetClassMetaData = $loadedClassMetaData[$args['target']];
                                $targetClassTable = $targetClassMetaData->table;
                                $targetClassPrimaryKey = $targetClassMetaData->getPrimaryKey();

                                // As not defined in file, let's create a join table
                                // corresponding to the target class table to which the current
                                // table is added
                                if (is_null($joinTable)) {
                                    $joinTable = $targetClassTable . '_' . $table;
                                }

                                $qb->createTable($joinTable);
                                $qb->addJoinColumn($table . '_id');
                                $qb->addJoinColumn($targetClassTable . '_id');

                                $qb->addForeignKey(
                                    $table . '_id',
                                    $table,
                                    $primaryKey
                                );

                                $qb->addForeignKey(
                                    $targetClassTable . '_id',
                                    $targetClassTable,
                                    $targetClassPrimaryKey
                                );
                            }
                        }
                    }
                }

                $qb->removeComma()->addEndDelimiter();
            }
        }

        $qb->getQuery()->execute();
    }
}