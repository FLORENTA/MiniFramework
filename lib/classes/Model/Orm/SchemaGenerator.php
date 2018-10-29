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
        $loadedClassMetaData =
            $this->em
            ->getClassMetaDataFactory()
            ->getLoadedClassMetaData();

        /** @var ClassMetaData $classMetaData */
        foreach ($loadedClassMetaData as $classMetaData) {

            $table     = $classMetaData->table;
            $fields    = $classMetaData->fields;
            $relations = $classMetaData->relations;
            $primary = null;

            if ($table) {

                $sql = "CREATE TABLE IF NOT EXISTS $table(";

                /** @var bool $primary the table primary key */
                $primary = $this->getPrimaryKey($fields);

                /**
                 * @var string $field
                 * @var array $value
                 */
                foreach ($fields as $field => $value) {

                    /** @var string|null $type */
                    $type    = $this->getType($value);

                    /** @var string|int|null $length */
                    $length  = $this->getLength($value);

                    /** @var bool $nullabe */
                    $nullabe = $this->isNullable($value);

                    $sql .= "$field ";

                    if (!is_null($type)) {
                        $sql .= $type === 'string' ? 'VARCHAR' : $type . ' ';
                    }

                    if (!is_null($length)) {
                        $sql .= '(' . $length . ')';
                    }

                    if (!$nullabe) {
                        $sql .= " NOT NULL";
                    } else {
                        $sql .= " NULL";
                    }


                    $sql .= ", ";
                }

                if (!is_null($primary)) {
                    $sql .= "PRIMARY KEY ($primary), ";
                } else {
                    $sql .= "PRIMARY KEY (id), ";
                }

                foreach ($relations as $relation => $data) {
                    if ($relation === RelationType::MANY_TO_ONE) {
                        foreach ($data as $attribute => $args) {
                            $joinColumn = $this->getJoinColumn($args);

                            /** @var ClassMetaData $targetClassMetaData */
                            $targetClassMetaData = $loadedClassMetaData[$args['target']];
                            $targetClassTable = $targetClassMetaData->table;
                            $targetClassPrimaryKey = $this->getPrimaryKey($targetClassMetaData->fields);

                            if (!is_null($joinColumn)) {
                                $sql .= "$joinColumn INT NOT NULL, ";
                                $sql .= "FOREIGN KEY ($joinColumn) ";
                            } else {
                                $joinColumn = $targetClassTable . '_id';
                                $sql .= "$joinColumn INT NOT NULL, ";
                                $sql .= "FOREIGN KEY ($joinColumn) ";
                            }

                            $sql .= "REFERENCES $targetClassTable($targetClassPrimaryKey), ";
                        }
                    }

                    if ($relation === RelationType::MANY_TO_MANY) {

                    }
                }

                $sql = rtrim($sql, ', ');

                $sql .= ");";

                $this->em->getConnection()->exec($sql);
            }
        }
    }

    /**
     * @param array $fields
     * @return string
     */
    private function getPrimaryKey($fields)
    {
        /** @var string $firstField */
        $firstField = array_keys($fields)[0];

        foreach ($fields as $field => $data) {
            if (isset($data['primary'])) {
                return $data['primary'];
            }
        }

        return $firstField;
    }

    /**
     * @param $value
     * @return string|null
     */
    private function getType($value)
    {
        return isset($value['type']) ? $value['type'] : null;
    }

    /**
     * @return string|int|null
     */
    private function getLength($value)
    {
        return isset($value['length']) ? $value['length'] : null;
    }

    /**
     * @param $value
     * @return bool
     */
    private function isNullable($value)
    {
        return isset($value['nullable']) ? $value['nullable'] : false;
    }

    /**
     * @param $value
     * @return string|null
     */
    private function getJoinColumn($value)
    {
        return isset($value['joinColumn']) ? $value['joinColumn'] : null;
    }

    /**
     * @param $value
     * @return string|null
     */
    private function getJoinTable($value)
    {
        return isset($value['joinColumn']) ? $value['joinColumn'] : null;
    }
}