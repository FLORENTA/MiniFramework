<?php

namespace Lib\Model\Orm;

use Lib\Model\Relation\RelationType;

/**
 * Class SchemaGenerator
 * @package Lib\Model\Orm
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

    /**
     * @todo, handling exception
     * Function to create all tables in database depending on yaml files
     * found in src/Resources/orm/mapping directory
     *
     * @throws \Exception
     */
    public function createSchema()
    {
        /** @var array $loadedClassMetaData */
        $loadedClassMetaData = $this->em->getClassMetaDataFactory()->getLoadedClassMetaData();

        /**
         * Will contain all the tables to create
         *
         * @var QueryBuilder $qb
         */
        $qb = new QueryBuilder($this->em);

        /** @var ClassMetaData $classMetaData */
        foreach ($loadedClassMetaData as $classMetaData) {

            $table  = $classMetaData->table;
            $fields = $classMetaData->fields;

            /** @var array $oneToOneRelations */
            $oneToOneRelations   = $classMetaData->getRelations(RelationType::ONE_TO_ONE);

            /** @var array $manyToOneRelations */
            $manyToOneRelations  = $classMetaData->getRelations(RelationType::MANY_TO_ONE);

            /** @var array $manyToManyRelations */
            $manyToManyRelations = $classMetaData->getRelations(RelationType::MANY_TO_MANY);

            $primary             = null;

            if ($table) {

                /* Init table creation */
                $qb->createTable($table);

                /** @var string|null $primaryKey the table primary key */
                $primaryKey = $classMetaData->getPrimaryKey();

                /**
                 * @var string $field
                 * @var array $fieldData
                 */
                foreach ($fields as $field => $fieldData) {

                    /** @var string|null $columnName */
                    $columnName = $classMetaData->getColumnName($fieldData, $field);

                    /** @var string|null $type */
                    $type       = $classMetaData->getType($fieldData);

                    /** @var string|int|null $length */
                    $length     = $classMetaData->getLength($fieldData);

                    /** @var bool $null */
                    $null       = $classMetaData->isNullable($fieldData);

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

                /**
                 * @todo, merge with many to one foreach loop
                 * Iterating over one to one relations
                 *
                 * @var string $relation
                 * @var array $data
                 */
                foreach ($oneToOneRelations as $data) {
                    if ($classMetaData->isOwningSide($data)) {
                        $this->createJoinColumn(
                            $data,
                            $classMetaData,
                            $loadedClassMetaData,
                            $qb
                        );
                    }
                }

                /**
                 * Iterating over many to one relations
                 *
                 * @var string $relation
                 * @var array $data
                 */
                foreach ($manyToOneRelations as $data) {
                    if ($classMetaData->isOwningSide($data)) {
                        $this->createJoinColumn(
                            $data,
                            $classMetaData,
                            $loadedClassMetaData,
                            $qb);
                    }
                }

                $qb->endTableCreation();

                /**
                 * Creating join tables for many to many relations
                 *
                 * @var string $relation
                 * @var array $data
                 */
                foreach ($manyToManyRelations as $data) {

                    // Checking owning side only
                    if ($classMetaData->isOwningSide($data)) {

                        /** @var null|string $joinColumn */
                        $joinTable             = $classMetaData->getJoinTable($data);

                        /** @var ClassMetaData $targetClassMetaData */
                        $targetClassMetaData   = $loadedClassMetaData[$data['target']];

                        /** @var string $targetClassTable */
                        $targetClassTable      = $targetClassMetaData->table;

                        /** @var string $targetClassPrimaryKey */
                        $targetClassPrimaryKey = $targetClassMetaData->getPrimaryKey();

                        // As not defined in file, let's create a join table
                        // corresponding to the target class table to which the current
                        // table is added
                        if (is_null($joinTable)) {
                            $joinTable = $targetClassTable . '_' . $table;
                        }

                        /** @var string $tableJoinColumn */
                        $tableJoinColumn = $table . '_id';

                        /** @var string $targetTableJoinColumn */
                        $targetTableJoinColumn = $targetClassTable . '_id';

                        $qb ->createTable($joinTable)
                            ->addJoinColumn($tableJoinColumn)
                            ->addJoinColumn($targetTableJoinColumn)
                            ->addForeignKey($tableJoinColumn, $table, $primaryKey)
                            ->addForeignKey($targetTableJoinColumn, $targetClassTable, $targetClassPrimaryKey)
                            ->endTableCreation();
                    }
                }
            }
        }

        /**
         * Creates all tables with joinColumns and joinTables
         * in a single transaction
         */
        $qb->getQuery()->execute();
    }

    /**
     * Function for one-to-one and many-to-one relations
     *
     * If the class is the owning side, the corresponding table will have
     * a join column
     *
     * @param $data
     * @param ClassMetaData $classMetaData
     * @param $loadedClassMetaData
     * @param QueryBuilder $qb
     */
    private function createJoinColumn($data, $classMetaData, &$loadedClassMetaData, &$qb)
    {
        /** @var null|string $joinColumn */
        $joinColumn = $classMetaData->getJoinColumn($data);

        /** @var ClassMetaData $targetClassMetaData */
        $targetClassMetaData   = $loadedClassMetaData[$data['target']];
        $targetClassTable      = $targetClassMetaData->table;
        $targetClassPrimaryKey = $targetClassMetaData->getPrimaryKey();

        // As not defined in file, let's create a join column
        // corresponding to the target class table to which '_id'
        // is added
        if (is_null($joinColumn)) {
            $joinColumn = $targetClassTable . '_id';
        }

        $qb ->addJoinColumn($joinColumn)
            ->addForeignKey($joinColumn, $targetClassTable, $targetClassPrimaryKey);
    }
}