<?php

namespace Classes\Model\Orm;

use Classes\Utils\Tools;

/**
 * Class ClassMetaData
 * @package Classes\Model\Orm
 */
class ClassMetaData implements ClassMetaDataInterface
{
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

    /**
     * @param string $class
     * @return $this
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @param array $fields
     * @return $this
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @param string $table
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @param array $columns
     * @return $this
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @param string $model
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
     * @return array
     */
    public function getRelations($type)
    {
        if (isset($this->relations[$type])) {
            return $this->relations[$type];
        }

        return [];
    }

    /**
     * Function to return the primary key for this class[table]
     *
     * @return string
     */
    public function getPrimaryKey()
    {
        if (is_array($this->fields)) {
            /** @var string $firstField */
            $firstField = array_keys($this->fields)[0];

            foreach ($this->fields as $field => $data) {
                if (isset($data['primary'])) {
                    return $data['primary'];
                }
            }

            return $firstField;
        }

        return null;
    }

    /**
     * @param $fieldData
     * @return string|null
     */
    public function getType($fieldData)
    {
        return isset($fieldData['type']) ? $fieldData['type'] : null;
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
     * @return string|int|null
     */
    public function getLength($fieldData)
    {
        return isset($fieldData['length']) ? $fieldData['length'] : null;
    }

    /**
     * @param array $fieldData
     * @return bool
     */
    public function isNullable($fieldData)
    {
        return isset($fieldData['nullable']) ? $fieldData['nullable'] : false;
    }

    /**
     * @param array $fieldData
     * @return string|null
     */
    public function getJoinColumn($fieldData)
    {
        return isset($fieldData['joinColumn']) ? $fieldData['joinColumn'] : null;
    }

    /**
     * @param array $fieldData
     * @return string|null
     */
    public function getJoinTable($fieldData)
    {
        return isset($fieldData['joinColumn']) ? $fieldData['joinColumn'] : null;
    }

    /**
     * @param array $fieldData
     * @return bool|null
     */
    public function isOwningSide($fieldData)
    {
        return isset($fieldData['inversedBy']);
    }
}