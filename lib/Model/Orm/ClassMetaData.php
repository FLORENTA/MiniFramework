<?php

namespace Lib\Model\Orm;

use Lib\Model\Exception\ClassMetaDataException;
use Lib\Model\Relation\RelationType;
use Lib\Utils\Tools;

/**
 * Class ClassMetaData
 * @package Lib\Model\Orm
 */
class ClassMetaData implements ClassMetaDataInterface
{
    /** @var null $name */
    public $name = null;

    /** @var string|null $class */
    public $class = null;

    /** @var array $fields */
    public $fields = [];

    /** @var array $columns */
    public $columns = [];

    /** @var string|null $model */
    public $model = null;

    /** @var string|null $table */
    public $table = null;

    /** @var array $relations */
    public $relations = [];

    /** @var array $entityProperties */
    private $entityProperties;

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string $class
     *
     * @return $this
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @param array $fields
     *
     * @return $this
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @param string $table
     *
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @param array $columns
     *
     * @return $this
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @param string $model
     *
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @param string $type
     * @param array $relation
     *
     * @return $this
     */
    public function setRelations($type, $relation)
    {
        $this->relations[$type] = $relation;

        return $this;
    }

    /**
     * Has the entity relations of a certain type with other entities ?
     *
     * @param string $type
     *
     * @return bool
     */
    public function hasRelations($type)
    {
        return isset($this->relations[$type]);
    }

    /**
     * Return the entity relations of a certain type
     *
     * @param string $type
     *
     * @return array
     */
    public function getRelations($type)
    {
        if ($this->hasRelations($type)) {
            return $this->relations[$type];
        }

        return [];
    }

    /**
     * Function to return the primary key for this class[table]
     *
     * @throws ClassMetaDataException
     * @return string|null
     */
    public function getPrimaryKey()
    {
        if (is_array($this->fields)) {
            /** @var string $firstField */
            $fields = array_keys($this->fields);

            if (empty($firstField = $fields[0])) {
                throw new ClassMetaDataException(
                    sprintf(
                        'Undefined primary key for entity %s.',
                        $this->name
                    )
                );
            }

            foreach ($this->fields as $field => $data) {
                if (isset($data['primary'])) {
                    return $data['primary'];
                }
            }

            /* If no primary: true defined for a field
             * return the first class field
             */
            return $firstField;
        }

        return null;
    }

    /**
     * @param array $fieldData
     * @return string|null
     */
    public function getType($fieldData)
    {
        return isset($fieldData['type'])
            ? $fieldData['type'] : null;
    }

    /**
     * @param array $fieldData
     * @param string $field
     * @return string
     */
    public function getColumnName($fieldData, $field)
    {
        return isset($fieldData['columnName'])
            ? $fieldData['columnName']
            : Tools::splitCamelCasedWords($field);
    }

    /**
     * @param array $fieldData
     * @return string|int|null
     */
    public function getLength($fieldData)
    {
        return isset($fieldData['length'])
            ? $fieldData['length'] : null;
    }

    /**
     * @param array $fieldData
     * @return bool
     */
    public function isNullable($fieldData)
    {
        return isset($fieldData['nullable'])
            ? $fieldData['nullable'] : false;
    }

    /**
     * @param array $fieldData
     * @return string|null
     */
    public function getJoinColumn($fieldData)
    {
        return isset($fieldData['joinColumn'])
            ? $fieldData['joinColumn'] : null;
    }

    /**
     * @param array $fieldData
     * @return string|null
     */
    public function getJoinTable($fieldData)
    {
        return isset($fieldData['joinTable'])
            ? $fieldData['joinTable'] : null;
    }

    /**
     * @param array $fieldData
     * @return bool|null
     */
    public function isOwningSide($fieldData)
    {
        return isset($fieldData['inversedBy']);
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function hasCascadePersist($data)
    {
        return !empty($c = $data['cascade']) && in_array('persist', $c);
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function hasCascadeRemove($data)
    {
        return !empty($c = $data['cascade']) && in_array('remove', $c);
    }

    /**
     * @return array
     */
    public function getEntityProperties()
    {
        /** @var array $fields */
        $fields  = array_keys($this->fields);

        $properties = [];

        /** @var string $field */
        foreach ($fields as $key => $field) {
            $properties[$key]['attribute'] = $field;
        }

        /** @var string $field */
        foreach ($this->columns as $key => $column) {
            $properties[$key]['column'] = $column;
        }

        /**
         * Many To One relations
         *
         * @var string $field
         * @var array $data
         */
        foreach ($this->getRelations(RelationType::MANY_TO_ONE) as $field => $data) {

            /** @var ClassMetaData $targetEntityMetaData */
            $targetEntityMetaData = ClassMetaDataFactory::getClassMetaData($data['target']);

            /** @var string $defaultJoinedColumn */
            $defaultJoinedColumn = $targetEntityMetaData->table . '_' . 'id';

            /** @var string $targetEntityManyToOneJoinedColumn */
            $targetEntityManyToOneJoinedColumn = isset($data['joinColumn'])
                ? $data['joinColumn']
                : $defaultJoinedColumn;

            $this->setEntityRelationProperties(
                $properties,
                $field,
                RelationType::MANY_TO_ONE,
                $field,
                $targetEntityMetaData->table,
                $data['target'],
                $targetEntityManyToOneJoinedColumn
            );

            if ($this->isOwningSide($data)) {
                $properties['relation'][$field]['inversedBy'] = $data['inversedBy'];
            }
        }

        /**
         * One To Many relations
         *
         * @var string $field
         * @var array $data
         */
        foreach ($this->getRelations(RelationType::ONE_TO_MANY) as $field => $data) {

            /** @var ClassMetaData $targetEntityMetaData */
            $targetEntityMetaData = ClassMetaDataFactory::getClassMetaData(($data['target']));

            $this->setEntityRelationProperties(
                $properties,
                $field,
                RelationType::ONE_TO_MANY,
                $field,
                $targetEntityMetaData->table,
                $data['target']
            );

            if (isset($data['mappedBy'])) {
                $properties['relation'][$field]['mappedBy'] = $data['mappedBy'];

                if (isset($data['cascade'])) {
                    $properties['relation'][$field]['cascade'] = $data['cascade'];
                }
            }
        }

        /**
         * @var string $field
         * @var array $data
         */
        foreach ($this->getRelations(RelationType::MANY_TO_MANY) as $field => $data) {

            /** @var ClassMetaData $targetEntityMetaData */
            $targetEntityMetaData = ClassMetaDataFactory::getClassMetaData($data['target']);
            $targetEntityTable = $targetEntityMetaData->table;

            $this->setEntityRelationProperties(
                $properties,
                $field,
                RelationType::MANY_TO_MANY,
                $field,
                $targetEntityTable,
                $data['target']
            );

            if (isset($data['mappedBy'])) {
                $properties['relation'][$field]['mappedBy'] = $data['mappedBy'];
            }

            if ($this->isOwningSide($data)) {
                $properties['relation'][$field]['inversedBy'] = $data['inversedBy'];
            }

            if (isset($data['joinTable'])) {
                $properties['relation'][$field]['joinTable'] = $data['joinTable'];
            } else {
                if ($this->isOwningSide($data)) {
                    $properties['relation'][$field]['joinTable'] =
                        $targetEntityTable . '_' . $this->table;
                } else {
                    $properties['relation'][$field]['joinTable'] =
                        $this->table . '_' . $targetEntityTable;
                }
            }
        }

        /**
         * One To Many relations
         *
         * @var string $field
         * @var array $data
         */
        foreach ($this->getRelations(RelationType::ONE_TO_ONE) as $field => $data) {

            /** @var ClassMetaData $targetEntityMetaData */
            $targetEntityMetaData = ClassMetaDataFactory::getClassMetaData($data['target']);

            $targetEntityManyToOneJoinedColumn = null;

            if (isset($data['inversedBy'])) {
                /** @var string $defaultJoinedColumn */
                $defaultJoinedColumn = $targetEntityMetaData->table . '_' . 'id';

                /** @var string $targetEntityManyToOneJoinedColumn */
                $targetEntityManyToOneJoinedColumn = isset($data['joinColumn'])
                    ? $data['joinColumn']
                    : $defaultJoinedColumn;
            }

            $this->setEntityRelationProperties(
                $properties,
                $field,
                RelationType::ONE_TO_ONE,
                $field,
                $targetEntityMetaData->table,
                $data['target'],
                $targetEntityManyToOneJoinedColumn
            );

            if (isset($data['mappedBy'])) {
                $properties['relation'][$field]['mappedBy'] = $data['mappedBy'];

                if (isset($data['cascade'])) {
                    $properties['relation'][$field]['cascade'] = $data['cascade'];
                }
            }

            if ($targetEntityMetaData->isOwningSide($data)) {
                $properties['relation'][$field]['inversedBy'] = $data['inversedBy'];
            }
        }

        return $this->entityProperties = $properties;
    }

    /**
     * Function to set entity properties info
     *
     * @param array $properties
     * @param string $key
     * @param string $type
     * @param string $name
     * @param string $table
     * @param string $class
     * @param null $column
     *
     * @return void
     */
    private function setEntityRelationProperties(
        &$properties,
        $key,
        $type,
        $name,
        $table,
        $class,
        $column = null
    )
    {
        $properties['relation'][$key]['type'] = $type;
        $properties['relation'][$key]['attribute'] = $name;
        $properties['relation'][$key]['table'] = $table;
        $properties['relation'][$key]['class'] = $class;

        // Owning side should have a column name defined
        if (!empty($column)) {
            $properties['relation'][$key]['joinColumn'] = $column;
        }
    }

    /**
     * Function to return all the entity relations
     *
     * @param null $type, the type of relations to return
     *
     * @return array
     */
    public function getFullEntityRelations($type = null)
    {
        if (isset($this->entityProperties['relation'])) {
            if (!is_null($type)) {
                return array_filter(
                    $this->entityProperties['relation'],
                    function ($relation) use ($type) {
                        return $relation['type'] === $type;
                    }
                );
            }

            return $this->entityProperties['relation'];
        }

        return [];
    }
}