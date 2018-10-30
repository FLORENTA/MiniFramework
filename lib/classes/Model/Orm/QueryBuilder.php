<?php

namespace Classes\Model\Orm;

use Classes\Model\Connection\PDOFactory;
use Classes\Utils\Cache;

/**
 * Class QueryBuilder
 * @package Classes
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

    public function insert()
    {
        $this->sql = "INSERT INTO";
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
     * @return array
     */
    public function getArrayResult()
    {
        return $this->stmt->fetchAll();
    }

    /**
     * @param null $fetchType
     * @return mixed
     */
    public function fetch($fetchType = null)
    {
        if ($fetchType === self::FETCH_OBJECT) {
            $this->stmt->setFetchMode(
                \PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE,
                $this->classToHydrate
            );
        }

        $this->stmt->execute();

        return $this->stmt->fetch();
    }

    /**
     * @param $fetchType
     * @return array
     */
    public function fetchAll($fetchType)
    {
        if ($fetchType === self::FETCH_OBJECT) {
            $this->stmt->setFetchMode(
                \PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE,
                $this->classToHydrate
            );
        }

        $this->stmt->execute();

        return $this->stmt->fetchAll();
    }

    /**
     * @return object
     */
    public function getSingleResult()
    {
        return $this->fetch(self::FETCH_OBJECT);
    }

    /**
     * @return array
     */
    public function getResult()
    {
        return $this->fetchAll(self::FETCH_OBJECT);
    }

    public function execute()
    {
        $this->stmt->execute();
    }

    /**
     * @param string $table
     */
    public function createTable($table)
    {
        $this->sql = "CREATE TABLE IF NOT EXISTS $table";
        $this->addStartDelimiter();
    }

    public function addStartDelimiter()
    {
        $this->sql .= "(";
    }

    public function addEndDelimiter()
    {
        $this->sql .= ");";
    }

    /**
     * @param string $columnName
     */
    public function addColumn($columnName)
    {
        $this->sql .= $columnName;
    }

    /**
     * @param string $type
     */
    public function addType($type)
    {
        $this->sql .= $type === 'string' ? ' VARCHAR' : ' ' . $type;
    }

    /**
     * @param string|int $length
     */
    public function addLength($length)
    {
        $this->sql .= "($length)";
    }

    /**
     * @param bool $null
     */
    public function addNull($null)
    {
        if (!$null) {
            $this->sql .= " NOT NULL, ";
        } else {
            $this->sql .= " NULL, ";
        }
    }

    /**
     * @param string $key
     */
    public function addPrimaryKey($key)
    {
        $this->sql .= "PRIMARY KEY ($key), ";
    }

    /**
     * @param string $joinColumn
     * @param string $targetTable
     * @param string $targetTablePrimaryKey
     */
    public function addForeignKey($joinColumn, $targetTable, $targetTablePrimaryKey)
    {
        $this->sql .= "FOREIGN KEY ($joinColumn) REFERENCES $targetTable($targetTablePrimaryKey), ";
    }

    /**
     * @param string $joinColumn
     */
    public function addJoinColumn($joinColumn)
    {
        $this->sql .= "$joinColumn INT NOT NULL, ";
    }

    public function addJoinTable()
    {

    }

    /**
     * @return $this
     */
    public function removeComma()
    {
        $this->sql = rtrim($this->sql, ', ');

        return $this;
    }
}