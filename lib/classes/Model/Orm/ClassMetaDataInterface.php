<?php

namespace Classes\Model\Orm;

/**
 * Interface ClassMetaDataInterface
 * @package Classes\Model\Orm
 */
interface ClassMetaDataInterface
{
    /**
     * @param string $class
     * @return $this
     */
    public function setClass($class);

    /**
     * @param array $fields
     * @return $this
     */
    public function setFields($fields);

    /**
     * @param string $table
     * @return $this
     */
    public function setTable($table);

    /**
     * @param array $columns
     * @return $this
     */
    public function setColumns($columns);

    /**
     * @param string $model
     * @return $this
     */
    public function setModel($model);

    /**
     * @param string $type
     * @param array $relation
     * @return $this
     */
    public function setRelations($type, $relation);
}