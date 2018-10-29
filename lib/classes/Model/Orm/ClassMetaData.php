<?php

namespace Classes\Model\Orm;

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
        return $this->relations[$type];
    }
}