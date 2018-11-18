<?php

namespace Lib\Model\Orm;

use Lib\Model\Connection\PDOFactory;
use Lib\Utils\Cache;

/**
 * Class QueryBuilder
 * @package Lib
 */
class QueryBuilder
{
    const SET = 'SET';
    const INNER_JOIN = 'INNER JOIN';
    const LEFT_JOIN = 'LEFT JOIN';
    const ON = 'ON';
    const FETCH_OBJECT = "FETCH_OBJECT";

    /** @var \PDOStatement */
    protected $stmt;

    /** @var array */
    protected $parameters = [];

    /** @var array */
    protected $values = [];

    /** @var string $class */
    protected $class;

    /** @var PDOFactory */
    protected $pdo;

    /** @var string */
    protected $classToHydrate;

    /** @var string $fields */
    protected $fields;

    /** @var Cache $cache */
    protected $cache;

    /** @var EntityManagerInterface $em */
    protected $em;

    /** @var string $sql */
    protected $sql;

    /**
     * QueryBuilder constructor.
     * @param EntityManagerInterface $entityManager
     * @param null $class
     */
    public function __construct(EntityManagerInterface $entityManager, $class = null)
    {
        $this->em             = $entityManager;
        $this->classToHydrate = $class;
    }

    /**
     * @param string $fields
     * @return $this
     */
    public function select($fields)
    {
        $this->sql = "SELECT $fields";

        return $this;
    }

    /**
     * @param string $table
     *
     * @return $this
     */
    public function insertInto($table)
    {
        $this->sql = "INSERT INTO $table";

        return $this;
    }

    /**
     * @param $table
     *
     * @return $this
     */
    public function update($table)
    {
        $this->sql = "UPDATE $table";

        return $this;
    }

    /**
     * @param string $fields
     *
     * @return $this
     */
    public function set($fields)
    {
        $this->sql .= " SET $fields";

        return $this;
    }

    /**
     * @return $this
     */
    public function delete()
    {
        $this->sql = "DELETE";

        return $this;
    }

    /**
     * @param string $table
     * @return $this
     */
    public function from($table)
    {
        $this->sql .= " FROM $table";

        return $this;
    }

    /**
     * @param string $clause
     * @return $this
     */
    public function where($clause)
    {
        $this->sql .= " WHERE $clause";

        return $this;
    }

    /**
     * @param string $clause
     * @return $this
     */
    public function orWhere($clause)
    {
        $this->sql .= " OR $clause";

        return $this;
    }

    /**
     * @param string $clause
     * @return $this
     */
    public function andWhere($clause)
    {
        $this->sql .= " AND $clause";

        return $this;
    }

    /**
     * @param string $parameter
     * @param mixed $value
     * @return $this
     */
    public function setParameter($parameter, $value)
    {
        $this->parameters[] = $parameter;
        $this->values[] = $value;

        return $this;
    }

    /**
     * @param array $parameters
     * @return $this
     */
    public function setParameters(array $parameters = [])
    {
        foreach ($parameters as $parameter => $value) {
            $this->parameters[] = $parameter;
            $this->values[] = $value;
        }

        return $this;
    }

    /**
     * @param string $table
     * @param string $joinType
     * @param string $condition
     * @return $this
     */
    public function join($table, $joinType, $condition)
    {
        $this->sql .= " " . self::INNER_JOIN . " $table $joinType $condition";

        return $this;
    }

    /**
     * @return $this
     */
    public function getQuery()
    {
        $this->stmt = $this->em->getConnection()->prepare($this->sql);

        foreach ($this->parameters as $key => $parameter) {
            $this->stmt->bindValue($parameter, $this->values[$key]);
        }

        return $this;
    }

    /**
     * @param integer $limit
     */
    public function setMaxResults($limit)
    {
        $this->sql .= " LIMIT $limit";
    }

    /**
     * @return mixed
     */
    public function fetch()
    {
        $this->execute();

        return $this->stmt->fetch();
    }

    /**
     * @return array
     */
    public function fetchAll()
    {
        $this->execute();

        return $this->stmt->fetchAll();
    }

    /**
     * @return array
     */
    public function getArrayResult()
    {
        return $this->fetchAll();
    }

    /**
     * @return object
     */
    public function getSingleResult()
    {
        return $this->setFetchObject()->fetch();
    }

    /**
     * Will return an array of objects
     *
     * @return array
     */
    public function getResult()
    {
        return $this->setFetchObject()->fetchAll();
    }

    /**
     * The statement to execute
     */
    public function execute()
    {
        $this->stmt->execute();
    }

    /**
     * @return $this
     */
    public function setFetchObject()
    {
        $this->stmt->setFetchMode(
            \PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE,
            $this->classToHydrate
        );

        return $this;
    }

    /**
     * @param string $table
     *
     * @return $this
     */
    public function createTable($table)
    {
        $this->sql .= "CREATE TABLE IF NOT EXISTS $table(";

        return $this;
    }

    /**
     * @param string $columnName
     *
     * @return $this;
     */
    public function addColumn($columnName)
    {
        $this->sql .= $columnName;

        return $this;
    }

    /**
     * @param string $type
     *
     * @return $this;
     */
    public function addType($type)
    {
        $this->sql .= $type === 'string' ? ' VARCHAR' : ' ' . $type;

        return $this;
    }

    /**
     * @return $this
     */
    public function addAutoIncrement()
    {
        $this->sql .= " AUTO_INCREMENT";

        return $this;
    }

    /**
     * @param string|int $length
     *
     * @return $this;
     */
    public function addLength($length)
    {
        $this->sql .= "($length)";

        return $this;
    }

    /**
     * @param bool $null
     *
     * @return $this;
     */
    public function addNull($null)
    {
        $this->sql .= !$null ? " NOT NULL, " : " DEFAULT NULL, ";

        return $this;
    }

    /**
     * @param string $key
     *
     * @return $this
     */
    public function addPrimaryKey($key)
    {
        $this->sql .= "PRIMARY KEY ($key), ";

        return $this;
    }

    /**
     * @param string $joinColumn
     * @param string $targetTable
     * @param string $targetTablePrimaryKey
     *
     * @return $this
     */
    public function addForeignKey($joinColumn, $targetTable, $targetTablePrimaryKey)
    {
        $this->sql .= "FOREIGN KEY ($joinColumn) REFERENCES $targetTable($targetTablePrimaryKey), ";

        return $this;
    }

    /**
     * @param string $joinColumn
     *
     * @return $this
     */
    public function addJoinColumn($joinColumn)
    {
        $this->sql .= "$joinColumn INT NOT NULL, ";

        return $this;
    }

    /**
     * @return $this
     */
    public function removeComma()
    {
        $this->sql = rtrim($this->sql, ', ');

        return $this;
    }

    /**
     * @return $this
     */
    public function endTableCreation()
    {
        $this->sql = rtrim($this->sql, ', ');
        $this->sql .= ");";

        return $this;
    }

    /**
     * @return \PDOStatement
     */
    public function getStatement()
    {
        return $this->stmt;
    }
}