<?php

namespace Lib\Model\Orm;

use Lib\Model\Connection\PDOFactory;
use Lib\Throwable\QueryBuilderException;

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

    /** @var string|null $class */
    protected $class;

    /** @var string|null $table */
    protected $table;

    /** @var PDOFactory */
    protected $pdo;

    /** @var string */
    protected $classToHydrate;

    /** @var EntityManagerInterface $em */
    protected $em;

    /** @var string $sql */
    protected $sql;

    /** @var PartBuilder $partBuilder */
    private $partBuilder;

    /**
     * QueryBuilder constructor.
     * @param EntityManagerInterface $entityManager
     * @param null $class
     * @param null $table
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        $class = null,
        $table = null
    )
    {
        $this->em             = $entityManager;
        $this->classToHydrate = $class;
        $this->table          = $table;
        $this->partBuilder    = new PartBuilder();
    }

    /**
     * @param string $fields
     * @return $this
     * @throws QueryBuilderException
     */
    public function select($fields)
    {
        if ($this->partBuilder->hasUpdate()) {
            $this->launchQueryBuilderException("SELECT", "UPDATE");
        }

        if ($this->partBuilder->hasInsert()) {
            $this->launchQueryBuilderException("SELECT", "INSERT");
        }

        if ($this->partBuilder->hasDelete()) {
            $this->launchQueryBuilderException("SELECT", "DELETE");
        }

        if (!$this->partBuilder->hasSelect()) {
            $this->partBuilder->setSelect($fields);
        }

        return $this;
    }

    /**
     * @param string $table
     *
     * @return $this
     */
    public function insertInto($table)
    {
        if (!$this->partBuilder->hasInsert()) {
            $this->partBuilder->setInsert($table);
        }

        return $this;
    }

    /**
     * @param string $table
     *
     * @return $this
     * @throws QueryBuilderException
     */
    public function update($table)
    {
        if ($this->partBuilder->hasSelect()) {
            $this->launchQueryBuilderException("UPDATE", "SELECT");
        }

        if ($this->partBuilder->hasInsert()) {
            $this->launchQueryBuilderException("UPDATE", "INSERT");
        }

        if ($this->partBuilder->hasDelete()) {
            $this->launchQueryBuilderException("UPDATE", "DELETE");
        }

        if (!$this->partBuilder->hasUpdate()) {
            $this->partBuilder->setUpdate($table);
        }

        return $this;
    }

    /**
     * @param string $fields
     *
     * @return $this
     */
    public function set($fields)
    {
        if ($this->partBuilder->hasSet()) {
            $this->partBuilder->setSet($fields);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws QueryBuilderException
     */
    public function delete()
    {
        if ($this->partBuilder->hasSelect()) {
            $this->launchQueryBuilderException("DELETE", "SELECT");
        }

        if ($this->partBuilder->hasInsert()) {
            $this->launchQueryBuilderException("DELETE", "INSERT");
        }

        if ($this->partBuilder->hasUpdate()) {
            $this->launchQueryBuilderException("DELETE", "UPDATE");
        }

        return $this;
    }

    /**
     * @param string $table
     * @return $this
     */
    public function from($table = null)
    {
        /** @var string $table */
        $table = $table ?: $this->table;

        if (!$this->partBuilder->hasFrom()) {
            $this->partBuilder->setFrom($table);
        }

        return $this;
    }

    /**
     * @param string $clause
     * @return $this
     */
    public function where($clause)
    {
        if (!$this->partBuilder->hasWhere()) {
            $this->partBuilder->setWhere($clause);
        }

        return $this;
    }

    /**
     * @param string $clause
     * @return $this
     * @throws QueryBuilderException
     */
    public function orWhere($clause)
    {
        if (!$this->partBuilder->hasWhere()) {
            throw new QueryBuilderException(
                'Missing WHERE clause in order to apply OrWhere clause.'
            );
        }


        $this->partBuilder->addOrWhere($clause);

        return $this;
    }

    /**
     * @param string $clause
     * @return $this
     * @throws QueryBuilderException
     */
    public function andWhere($clause)
    {
        if (!$this->partBuilder->hasWhere()) {
            throw new QueryBuilderException(
                'Missing WHERE clause in order to apply AndWhere clause.'
            );
        }

        $this->partBuilder->addAndWhere($clause);

        return $this;
    }

    /**
     * @param string $table
     * @param string $joinType
     * @param string $condition
     *
     * @return $this
     */
    public function join($table, $joinType, $condition)
    {
        $this->partBuilder->addJoin($table, $joinType, $condition);

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
     * @param int $limit
     *
     * @return $this
     */
    public function setMaxResults($limit)
    {
        if (!$this->partBuilder->hasMaxResult()) {
            $this->partBuilder->setMaxResult($limit);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function getQuery()
    {
        if ($this->partBuilder->hasSelect()) {
            $this->sql .= $this->partBuilder->getSelect();
            if (!$this->partBuilder->hasFrom()) {
                // If the user does not set from in their query
                // Let's consider the table is that of the default entity
                // (entity linked to the calling model)
                $this->from();
            }
            $this->sql .= $this->partBuilder->getFrom();
        }

        if ($this->partBuilder->hasUpdate()) {
            $this->sql .= $this->partBuilder->getUpdate();
        }

        if ($this->partBuilder->hasDelete()) {
            $this->sql .= $this->partBuilder->getDelete();
        }

        if (!$this->partBuilder->hasJoins()) {
            foreach ($this->partBuilder->getJoins() as $join) {
                $this->sql .= $join;
            }
        }

        if ($this->partBuilder->hasWhere()) {
            $this->sql .= $this->partBuilder->getWhere();
        }

        if ($this->partBuilder->hasAndWheres()) {
            foreach ($this->partBuilder->getAndWheres() as $andWhere) {
                $this->sql .= $andWhere;
            }
        }

        if ($this->partBuilder->hasOrWheres()) {
            foreach ($this->partBuilder->getOrWheres() as $andWhere) {
                $this->sql .= $andWhere;
            }
        }

        if ($this->partBuilder->hasMaxResult()) {
            $this->sql .= $this->partBuilder->getMaxResult();
        }

        $this->stmt = $this->em->getConnection()->prepare($this->sql);

        foreach ($this->parameters as $key => $parameter) {
            $this->stmt->bindValue($parameter, $this->values[$key]);
        }

        return $this;
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

    /**
     * @param string $action
     * @param string $contain
     *
     * @throws QueryBuilderException
     */
    private function launchQueryBuilderException($action, $contain)
    {
        throw new QueryBuilderException(
            sprintf(
                "Cannot apply %s in a query already containing a(n) %s statement.", $action, $contain
            )
        );
    }
}