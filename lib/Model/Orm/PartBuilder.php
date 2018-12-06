<?php

namespace Lib\Model\Orm;

/**
 * Class PartBuilder
 * @package Lib\Model\Orm
 */
class PartBuilder
{
    /** @var null $select */
    private $select = null;

    /** @var null $update */
    private $update = null;

    /** @var null $delete */
    private $delete = null;

    /** @var null $insert */
    private $insert = null;

    /** @var null $from */
    private $from = null;

    /** @var null $where */
    private $where = null;

    /** @var array $andWheres */
    private $andWheres = [];

    /** @var array $orWheres */
    private $orWheres = [];

    /** @var array $joins */
    private $joins = [];

    /** @var int $maxResults */
    private $maxResults;

    /** @var null $set */
    private $set = null;

    /**
     * @param string $fields
     */
    public function setSelect($fields)
    {
        $this->select = "SELECT $fields";
    }

    /**
     * @return bool
     */
    public function hasSelect()
    {
        return !is_null($this->select);
    }

    /**
     * @return string|null
     */
    public function getSelect()
    {
        return $this->select;
    }

    /**
     * @param string $table
     */
    public function setUpdate($table)
    {
        $this->update = "UPDATE $table";
    }

    /**
     * @return bool
     */
    public function hasUpdate()
    {
        return !is_null($this->update);
    }

    /**
     * @return string
     */
    public function getUpdate()
    {
        return $this->update;
    }

    public function setDelete()
    {
        $this->delete = "DELETE";
    }

    /**
     * @return bool
     */
    public function hasDelete()
    {
        return !is_null($this->delete);
    }

    /**
     * @return string
     */
    public function getDelete()
    {
        return $this->delete;
    }

    /**
     * @param string $table
     */
    public function setInsert($table)
    {
        $this->insert = "INSERT INTO $table";
    }

    /**
     * @return bool
     */
    public function hasInsert()
    {
        return !is_null($this->insert);
    }

    /**
     * @return null|string
     */
    public function getInsert()
    {
        return $this->insert;
    }

    /**
     * @param string $table
     */
    public function setFrom($table)
    {
        $this->from = " FROM $table";
    }

    /**
     * @return bool
     */
    public function hasFrom()
    {
        return !is_null($this->from);
    }

    /**
     * @return null|string
     */
    public function getFrom()
    {
        return $this->from;
    }


    /**
     * @param string $fields
     */
    public function setSet($fields)
    {
        $this->set = " SET $fields";
    }

    /**
     * @return bool
     */
    public function hasSet()
    {
        return !is_null($this->set);
    }

    /**
     * @return null|string
     */
    public function getSet()
    {
        return $this->set;
    }

    /**
     * @param string $clause
     */
    public function setWhere($clause)
    {
        $this->where = " WHERE $clause";
    }

    /**
     * @return bool
     */
    public function hasWhere()
    {
        return !is_null($this->where);
    }

    /**
     * @return null|string
     */
    public function getWhere()
    {
        return $this->where;
    }

    /**
     * @param string $clause
     */
    public function addOrWhere($clause)
    {
        $this->orWheres[] = " OR $clause";
    }

    /**
     * @return bool
     */
    public function hasOrWheres()
    {
        return !empty($this->orWheres);
    }

    /**
     * @return array
     */
    public function getOrWheres()
    {
        return $this->orWheres;
    }

    /**
     * @param string $clause
     */
    public function addAndWhere($clause)
    {
        $this->andWheres[] = " AND $clause";
    }

    /**
     * @return bool
     */
    public function hasAndWheres()
    {
        return !empty($this->andWheres);
    }

    /**
     * @return array
     */
    public function getAndWheres()
    {
        return $this->andWheres;
    }

    /**
     * @param string $table
     * @param string $joinType
     * @param string $condition
     */
    public function addJoin($table, $joinType, $condition)
    {
        $this->joins[] = " " . QueryBuilder::INNER_JOIN . " $table $joinType $condition";
    }

    /**
     * @return bool
     */
    public function hasJoins()
    {
        return !empty($this->joins);
    }

    /**
     * @return array
     */
    public function getJoins()
    {
        return $this->joins;
    }

    /**
     * @param string $limit
     */
    public function setMaxResult($limit)
    {
        $this->maxResults = " LIMIT $limit";
    }

    /**
     * @return bool
     */
    public function hasMaxResult()
    {
        return !is_null($this->maxResults);
    }

    /**
     * @return int|null
     */
    public function getMaxResult()
    {
        return $this->maxResults;
    }
}