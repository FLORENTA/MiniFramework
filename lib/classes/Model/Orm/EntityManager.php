<?php

namespace Classes\Model\Orm;

use Classes\Http\Session;
use Classes\Model\Connection\PDOFactory;
use Classes\Model\Model;
use Classes\Model\Relation\RelationType;

/**
 * Class EntityManager
 * @package Model\Orm
 */
class EntityManager implements EntityManagerInterface
{
    const PERSIST = 'PERSIST';
    const UPDATE = 'UPDATE';
    const REMOVE = 'REMOVE';

    /** @var \PDO $pdo */
    private $pdo;

    /** @var ClassMetaDataFactory $classesMetaData */
    private $classMetaDataFactory;

    /** @var DatabaseMetaData $databaseMetaData */
    private $databaseMetaData;

    /** @var Session $session */
    private $session;

    /**
     * EntityManager constructor.
     *
     * @param PDOFactory $PDOFactory
     * @param ClassMetaDataFactory $classMetaDataFactory
     * @param DatabaseMetaData $databaseMetaData
     * @param Session $session
     */
    public function __construct(
        PDOFactory $PDOFactory,
        ClassMetaDataFactory $classMetaDataFactory,
        DatabaseMetaData $databaseMetaData,
        Session $session
    )
    {
        $this->pdo                   = $PDOFactory::getConnexion();
        $this->classMetaDataFactory  = $classMetaDataFactory;
        $this->databaseMetaData      = $databaseMetaData;
        $this->session               = $session;
    }

    /**
     * @return \PDO
     */
    public function getConnection()
    {
        return $this->pdo;
    }

    /**
     * Function to return to meta data of a class
     *
     * @param string|object|array $entity
     * @return ClassMetaData
     */
    public function getClassMetaData($entity)
    {
        return $this->classMetaDataFactory->getClassMetaData($entity);
    }

    /**
     * @return ClassMetaDataFactory
     */
    public function getClassMetaDataFactory()
    {
        return $this->classMetaDataFactory;
    }

    /**
     * @param array $entityProperties
     * @return array
     */
    public function getRelations($entityProperties)
    {
        return isset($entityProperties['relation']) ? $entityProperties['relation'] : [];
    }

    /**
     * @param object $entity
     * @return mixed|void
     * @throws \Exception
     */
    public function persist($entity)
    {
        /** @var ClassMetaData $classMetaData */
        $classMetaData = $this->getClassMetaData($entity);

        /** @var string $table */
        $table = $classMetaData->table;

        /** @var array $entityProperties */
        $entityProperties = $this->getEntityProperties($entity);

        try {
            $this->insertUpdateOperation(
                $entity,
                $entityProperties,
                $table,
                self::PERSIST
            );

            $lastInsertId = $this->pdo->lastInsertId();

            $relations = $this->getRelations($entityProperties);

            // Located after execute statement because needing an id
            // For the many to many association between the new entities
            foreach ($relations as $relation) {
                if ($relation['type'] === RelationType::MANY_TO_MANY) {
                    $this->hydrateManyToManyRelation(
                        $relation,
                        self::PERSIST,
                        $entity,
                        $lastInsertId,
                        $table
                    );
                }
            }
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * @param object $entity
     * @return mixed|void
     * @throws \Exception
     */
    public function update($entity)
    {
        try {
            /** @var ClassMetaData $classMetaData */
            $classMetaData = $this->getClassMetaData($entity);

            /** @var string $table */
            $table = $classMetaData->table;

            /** @var array $entityProperties */
            $entityProperties = $this->getEntityProperties($entity);

            $this->insertUpdateOperation(
                $entity,
                $entityProperties,
                $table,
                self::UPDATE
            );

            $relations = $this->getRelations($entityProperties);

            foreach ($relations as $relation) {
                if ($relation['type'] === RelationType::MANY_TO_MANY) {
                    $this->hydrateManyToManyRelation(
                        $relation,
                        self::UPDATE,
                        $entity,
                        null,
                        $table
                    );
                }
            }

        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * @param array|object $entity
     * @return mixed|void
     * @throws \Exception
     */
    public function remove($entity)
    {
        /** @var ClassMetaData $classMetaData */
        $classMetaData = $this->getClassMetaData($entity);

        /** @var string $table */
        $table = $classMetaData->table;

        /** @var array $entityProperties */
        $entityProperties = $this->getEntityProperties($entity);

        /** @var QueryBuilder $query */
        $query = $this->createQueryBuilder();

        try {
            if (is_array($entity)) {
                $query->delete()->from($table)->getQuery()->execute();
            } else {
                $id = $entity->getId();

                $query->delete()
                    ->from($table)
                    ->where("id = :id")
                    ->setParameter("id", $id)
                    ->getQuery()->execute();

                $relations = $this->getRelations($entityProperties);

                foreach ($relations as &$relation) {
                    $type = $relation['type'];

                    $str = null;

                    /** @var QueryBuilder $query */
                    $query = $this->createQueryBuilder();

                    $query = $query->delete();

                    if ($type === RelationType::ONE_TO_MANY) {
                        $targetTable = $relation['table'];
                        $targetColumn = $relation['mappedBy'] . "_id";

                        $query->from($targetTable)
                            ->where("$targetColumn = :id")
                            ->setParameter("id", $id);
                    }

                    if ($type === RelationType::MANY_TO_MANY) {
                        $joinTable = $relation['joinTable'];
                        $field = $table . "_id";

                        $query->from($joinTable)
                            ->where("$field = :id")
                            ->setParameter("id", $id);
                    }

                    /* Must not delete the entity linked by a ManyToOne relation ... */
                    /* & condition to prevent from having $str null */
                    if (!is_null($str) && $type !== RelationType::MANY_TO_ONE) {
                        $query->getQuery()->getResult();
                    }
                }
            }

        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * For insert operations
     *
     * @param array $properties
     * @param string $entity
     * @return string
     */
    public function getFields($properties, $entity)
    {
        /** @var array $tablesColumns */
        $tablesColumns = $this->databaseMetaData->getTablesColumns();

        /** @var ClassMetaData $classMetaData */
        $classMetaData = $this->getClassMetaData($entity);

        /** @var string $table */
        $table = $classMetaData->table;

        // See insertUpdateOperation() for this->entity
        $fields = "";

        foreach ($properties as $key => $property) {
            /* checking that the column exists in table */
            if (is_array($property) && $key !== 'relation') {

                $targetEntityTableColumn = $property['column'];
                $targetEntityAttribute = $property['attribute'];

                // Checking whether the target table column exists or not
                // Indeed, the target entity attribute many not be in database
                if (in_array($targetEntityTableColumn, $tablesColumns[$table])) {
                    $fields .= $targetEntityTableColumn . " = :$targetEntityAttribute, ";
                } else {
                    unset($properties[$key]);
                }
            }

            if ($key === 'relation') {

                /* For each relation attribute in the class */
                /* Hydrating the column corresponding to the joined class */
                $relations = &$property;

                foreach ($relations as $relation) {

                    $type = $relation['type'];
                    $targetEntityJoinedColumn = null;

                    // Many not be defined in case of One to many
                    if (isset($relation['column'])) {
                        $targetEntityJoinedColumn = $relation['column'];
                    }

                    if (isset($relation['attribute'])) {
                        $targetEntityAttribute = $relation['attribute'];
                    }

                    if ($type === RelationType::MANY_TO_ONE) {
                        if (in_array($targetEntityJoinedColumn, $tablesColumns[$table]) ) {
                            $fields .= $targetEntityJoinedColumn . " = :$targetEntityAttribute, ";
                        }
                    }
                }
            }
        }

        return rtrim($fields, ', ');
    }

    /**
     * @param string $table
     * @param string $fields
     * @return bool|\PDOStatement
     */
    public function prepareInsertSqlStatement($table, $fields)
    {
        $sql = "INSERT INTO $table SET $fields";

        return $this->pdo->prepare($sql);
    }

    /**
     * @param $table
     * @param $fields
     * @param $entity
     *
     * @return \PDOStatement
     */
    public function prepareUpdateSqlStatement($table, $fields, $entity)
    {
        $sql = "UPDATE $table 
                SET $fields 
                WHERE id='{$entity->getId()}'";

        return $this->pdo->prepare($sql);
    }

    /**
     * @param $entity
     * @param $properties
     * @param \PDOStatement $stmt
     */
    public function hydrateFields($entity, $properties, &$stmt)
    {
        /* Hydrating the owner class */
        foreach ($properties as $key => $property) {
            if (is_array($property) && $key !== 'relation') {

                /** @var string $attribute */
                $attribute = $property['attribute'];

                $method = 'get' . ucfirst($attribute);

                if (is_array($value = $entity->$method())) {
                    $value = serialize($value);
                }

                $stmt->bindValue($attribute, $value);
            }

            if ($key === 'relation') {
                $this->hydrateManyToOneRelation($property, $stmt, $entity);
            }
        }
    }

    /**
     * @param $relations
     * @param \PDOStatement $stmt
     * @param $entity
     */
    public function hydrateManyToOneRelation(&$relations, &$stmt, &$entity)
    {
        /* Hydrating the joined class(es) if ManyToOne side */
        /* Checking that the $type exist (see above) and not OneToMany side */
        foreach ($relations as $relation) {

            /** @var string $attribute */
            $attribute = $relation['attribute'];

            if ($relation['type'] === RelationType::MANY_TO_ONE) {
                if (preg_match('/_id/', $relation['column'])) {
                    $method = 'get' . ucfirst($attribute);
                    $stmt->bindValue(
                        $attribute,
                        $entity->$method()->getId()
                    );
                }
            }
        }
    }

    /**
     * @param array $relation
     * @param string $operationType
     * @param null $entity
     * @param null $lastInsertId
     * @param null $table
     */
    public function hydrateManyToManyRelation(
        $relation,
        $operationType,
        $entity = null,
        $lastInsertId = null,
        $table = null
    )
    {
        /** @var string $targetTable */
        $targetTable = $relation['table'];

        /** @var string $joinTable */
        $joinTable = $relation['joinTable'];

        /** @var string $getMethod */
        $getMethod = 'get' . ucfirst($relation['attribute']);

        // E.g : user_id = :user
        $fields = $targetTable . '_id = :' . $targetTable . ', ';

        // E.g : game_id = :game
        $fields .= $table . '_id = :' . $table;

        $stmt = $this->pdo->prepare("INSERT INTO $joinTable SET $fields");

        // Avoid put the same entities twice !
        $linkedEntity = $entity->$getMethod();

        if (empty($linkedEntity)) {
            return;
        }

        $stmt->bindValue($targetTable, $linkedEntity->getId());

        $id = null;

        if ($operationType === self::PERSIST) {
            $id = $lastInsertId;
        }

        if ($operationType === self::UPDATE) {
            $id = $entity->getId();
        }

        if (is_null($id)) {
            throw new \RuntimeException(); // Todo add a message
        }

        $stmt->bindValue($table, $id);

        $stmt->execute();
    }

    /**
     * @param string $entity
     * @param array $properties
     * @param string $table
     * @param $operationType
     */
    public function insertUpdateOperation(
        $entity,
        $properties,
        $table,
        $operationType
    )
    {
        /** @var string $fields */
        $fields = $this->getFields($properties, $entity);

        // Prepare the statement
        if ($operationType === self::PERSIST) {
            /** @var \PDOStatement $stmt */
            $stmt = $this->prepareInsertSqlStatement(
                $table,
                $fields
            );
        }

        if ($operationType === self::UPDATE) {
            /** @var \PDOStatement $stmt */
            $stmt = $this->prepareUpdateSqlStatement(
                $table,
                $fields,
                $entity
            );
        }

        /* Hydrate the fields corresponding to the
         * fields defined with getFields method
         */
        $this->hydrateFields($entity, $properties, $stmt);

        /* Execute pdo statement */
        $stmt->execute();
    }

    /**
     * @param string $entity
     * @return array
     */
    public function getEntityProperties($entity)
    {
        /** @var ClassMetaData $classMetaData */
        $classMetaData = $this->getClassMetaData($entity);

        /** @var array $fields */
        $fields  = array_keys($classMetaData->fields);

        /** @var array $columns */
        $columns = array_keys($classMetaData->columns);

        /** @var string $table */
        $table = $classMetaData->table;

        $properties = [];

        /** @var string $field */
        foreach ($fields as $key => $field) {
            $properties[$key]['attribute'] = $field;
        }

        /** @var string $field */
        foreach ($columns as $key => $column) {
            $properties[$key]['column'] = $column;
        }

        // Many To One relations
        if ($classMetaData->hasRelations(RelationType::MANY_TO_ONE)) {
            $manyToOneRelations = $classMetaData->getRelations(
                RelationType::MANY_TO_ONE
            );

            /**
             * @var string $field
             * @var array $data
             */
            foreach ($manyToOneRelations as $field => $data) {

                /** @var ClassMetaData $targetEntityMetaData */
                $targetEntityMetaData = $this->getClassMetaData($data['target']);

                /** @var string $defaultJoinedColumn */
                $defaultJoinedColumn = $targetEntityMetaData->table . '_' . 'id';

                /** @var string $targetEntityManyToOneJoinedColumn */
                $targetEntityManyToOneJoinedColumn = $data['joinColumn'] ?: $defaultJoinedColumn;

                /**
                 * $properties will be filled with manyToOneRelations info
                 */
                $this->setProperties(
                    $properties,
                    $field,
                    RelationType::MANY_TO_ONE,
                    $field,
                    $targetEntityMetaData->table,
                    $data['target'],
                    $targetEntityManyToOneJoinedColumn
                );

                if ($classMetaData->isOwningSide($data)) {
                    $properties['relation'][$field]['inversedBy'] = $data['inversedBy'];
                }
            }
        }

        // One To Many relations
        if ($classMetaData->hasRelations(RelationType::ONE_TO_MANY)) {

            /** @var array $oneToManyRelations */
            $oneToManyRelations = $classMetaData->getRelations(
                RelationType::ONE_TO_MANY
            );

            /**
             * @var string $field
             * @var array $data
             */
            foreach ($oneToManyRelations as $field => $data) {

                /** @var ClassMetaData $targetEntityMetaData */
                $targetEntityMetaData = $this->getClassMetaData($data['target']);

                /**
                 * $properties will be filled with oneToManyRelations info
                 */
                $this->setProperties(
                    $properties,
                    $field,
                    RelationType::ONE_TO_MANY,
                    $field,
                    $targetEntityMetaData->table,
                    $data['target']
                );

                if (isset($data['mappedBy'])) {
                    $properties['relation'][$field]['mappedBy'] = $data['mappedBy'];
                }
            }
        }

        if ($classMetaData->hasRelations(RelationType::MANY_TO_MANY)) {
            $manyToManyRelations = $classMetaData->getRelations(
                RelationType::MANY_TO_MANY
            );

            /**
             * @var string $field
             * @var array $data
             */
            foreach ($manyToManyRelations as $field => $data) {

                /** @var ClassMetaData $targetEntityMetaData */
                $targetEntityMetaData = $this->getClassMetaData($data['target']);
                $targetEntityTable = $targetEntityMetaData->table;

                /**
                 * $properties will be filled with manyToManyRelations info
                 */
                $this->setProperties(
                    $properties,
                    $field,
                    RelationType::MANY_TO_MANY,
                    $field,
                    $targetEntityTable,
                    $data['target']
                );

                if (isset($data['mappedBy'])) {
                    $properties['relation'][$field]['mappedBy'] = $data['mappedBy'];
                    $properties['relation'][$field]['joinTable'] = $table . '_' . $targetEntityTable;
                }

                if ($classMetaData->isOwningSide($data)) {
                    $properties['relation'][$field]['inversedBy'] = $data['inversedBy'];
                    $properties['relation'][$field]['joinTable'] = $data['joinTable'];
                }
            }
        }

        $this->removedUselessFieldForDb($properties, $table);

        return $properties;
    }

    /**
     * @param array $properties
     * @param string $table
     */
    private function removedUselessFieldForDb(&$properties, $table)
    {
        $tablesColumns = $this->databaseMetaData->getTablesColumns();

        foreach ($properties as $key => $property) {
            /* checking that the column exists in table */
            /* Some class attributes may be used without any
               link with the database
            */
            if (is_array($property)
                && array_key_exists('column', $property)
                && !in_array($property['column'], $tablesColumns[$table])
            ) {
                unset($properties[$key]);
            }
        }
    }

    /**
     * @param array $properties
     * @param string $key
     * @param string $type
     * @param string $name
     * @param string $table
     * @param string $class
     * @param null $column
     */
    private function setProperties(
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

        if (!empty($column)) {
            $properties['relation'][$key]['column'] = $column;
        }
    }

    /**
     * @param array $properties
     * @return string
     */
    public function transformEntityColumnsNameToEntityAttributes(&$properties)
    {
        $fields = '';

        foreach ($properties as $key => $property) {

            if ($key !== 'relation') {
                $column = $property['column'];
                $attribute = $property['attribute'];

                $fields .=  $column . ' AS ' . $attribute . ', ';
            }

            if ($key === 'relation') {
                // Check as no column on the OneToMany side
                foreach ($property as $relation) {

                    if ($relation['type'] === RelationType::MANY_TO_ONE) {
                        $column = $relation['column'];
                        $attribute = $relation['attribute'];

                        $fields .= $column . ' AS ' . $attribute . ', ';
                    }
                }
            }
        }

        return rtrim($fields, ', ');
    }

    /**
     * @param string $className
     * @return Model
     * @throws \Exception
     */
    public function getEntityModel($className)
    {
        try {
            /** @var ClassMetaData $classMetaData */
            $classMetaData = $this->getClassMetaData($className);

            /** @var string $model */
            $model = $classMetaData->model;

            /** @var Model $model */
            return new $model($this, $classMetaData, $this->session);

        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * @param null $class
     * @return QueryBuilder
     */
    public function createQueryBuilder($class = null)
    {
        return new QueryBuilder($this, $class);
    }

    /**
     * @return DatabaseMetaData
     */
    public function getDatabaseMetaData()
    {
        return $this->databaseMetaData;
    }
}