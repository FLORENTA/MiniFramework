<?php

namespace Lib\Model\Orm;

use Lib\Http\Session;
use Lib\Model\Connection\PDOFactory;
use Lib\Model\Model;
use Lib\Model\Relation\RelationType;

/**
 * Class EntityManager
 * @package Model\Orm
 */
class EntityManager implements EntityManagerInterface
{
    const PERSIST = 'PERSIST';
    const UPDATE  = 'UPDATE';
    const REMOVE  = 'REMOVE';

    /** @var \PDO $pdo */
    private $pdo;

    /** @var ClassMetaDataFactory $classesMetaData */
    private $classMetaDataFactory;

    /** @var DatabaseMetaData $databaseMetaData */
    private $databaseMetaData;

    /** @var Session $session */
    private $session;

    /** @var ClassMetaData $classMetaData */
    private $classMetaData;

    /** @var string $table */
    private $table;

    /** @var array */
    private $entityProperties;

    /** @var array $persistedEntities */
    private $persistedEntities = [];

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
     * Function to return all the entity relations
     *
     * A filter can be applied to return relations of a specific type
     *
     * @param null $type
     *
     * @return array
     */
    private function getRelations($type = null)
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

    /**
     * Function to set the class meta data corresponding to the entity
     *
     * @param object $entity
     */
    public function setClassMetaData(&$entity)
    {
        /** @var ClassMetaData $classMetaData */
        $this->classMetaData    = $this->getClassMetaData($entity);
        $this->table            = $this->classMetaData->table;
        $this->entityProperties = $this->getEntityProperties();
    }

    /**
     * @param object $entity
     *
     * @return void
     */
    public function persist($entity)
    {
        // Avoid persisting twice this entity
        if (!in_array($entity, $this->persistedEntities)) {

            $this->setClassMetaData($entity);
            $this->insertUpdateOperation($entity, self::PERSIST);

            $lastInsertId = $this->pdo->lastInsertId();

            /** @var string|null $entityPrimaryKey */
            $entityPrimaryKey = $this->classMetaData->getPrimaryKey();
            $setMethod = 'set' . ucfirst($entityPrimaryKey);

            // Hydrate entity primary key
            if (method_exists($entity, $setMethod)) {
                $entity->$setMethod($lastInsertId);
            }

            $this->persistedEntities[] = $entity;
            $this->handleOneToOneRelations($entity)
                 ->handleOneToManyRelations($entity)
                 ->handleManyToManyRelations($entity);
        }
    }

    /**
     * @param object $entity
     *
     * @return $this
     */
    public function handleOneToOneRelations(&$entity)
    {
        $this->cascadePersist(
            $this->getRelations(RelationType::ONE_TO_ONE),
            $entity,
            RelationType::ONE_TO_ONE
        );

        return $this;
    }

    /**
     * @param object $entity
     *
     * @return $this
     */
    public function handleOneToManyRelations(&$entity)
    {
        $this->cascadePersist(
            $this->getRelations(RelationType::ONE_TO_MANY),
            $entity,
            RelationType::ONE_TO_MANY
        );

        return $this;
    }

    /**
     * @param object $entity
     */
    public function handleManyToManyRelations(&$entity)
    {
        $this->setClassMetaData($entity);

        /** @var array $relations */
        $manyToManyRelations = $this->getRelations(RelationType::MANY_TO_MANY);

        // Only the owning side of the relation
        array_walk(
            $manyToManyRelations,
            function($relation) use ($entity) {
                if (isset($relation['inversedBy'])) {
                    $this->hydrateManyToManyRelation($relation, $entity);
                }
            }
        );
    }

    /**
     * @param array $relations
     * @param object $entity
     * @param string $type
     *
     * @return void
     */
    public function cascadePersist($relations, &$entity, $type)
    {
        foreach ($relations as $attribute => $data) {
            if ($this->classMetaData->cascadePersist($data)) {
                $getMethod = 'get' . ucfirst($attribute);
                if ($type === RelationType::ONE_TO_ONE) {
                    $this->persist($entity->$getMethod());
                    continue;
                }

                if ($type === RelationType::ONE_TO_MANY) {
                    foreach ($entity->$getMethod() as $targetEntity) {
                        $this->persist($targetEntity);
                    }
                }
            }
        }
    }

    /**
     * @param object $entity
     * @return mixed|void
     * @throws \Exception
     */
    public function update($entity)
    {
        /** @var ClassMetaData $classMetaData */
        $classMetaData = $this->getClassMetaData($entity);

        /** @var string $table */
        $table = $classMetaData->table;

        /** @var array $entityProperties */
        $entityProperties = $this->getEntityProperties();

        $this->insertUpdateOperation(
            $entity,
            self::UPDATE
        );

        /** @var array $relations */
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

                /** @var array $relations */
                $relations = $this->getRelations();

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
     * @param string $entity
     *
     * @return string
     */
    public function getFields($entity)
    {
        /** @var array $tablesColumns */
        $tablesColumns = $this->databaseMetaData->getTablesColumns();

        /** @var ClassMetaData $classMetaData */
        $classMetaData = $this->getClassMetaData($entity);

        /** @var string $table */
        $table = $classMetaData->table;

        // See insertUpdateOperation() for this->entity
        $fields = "";

        foreach ($this->entityProperties as $key => $property) {
            /* checking that the column exists in table */
            if (is_array($property) && $key !== 'relation') {

                $targetEntityTableColumn = $property['column'];
                $targetEntityAttribute = $property['attribute'];

                // Checking whether the target table column exists or not
                // Indeed, the target entity attribute many not be in database
                if (in_array($targetEntityTableColumn, $tablesColumns[$table])) {
                    $fields .= $targetEntityTableColumn . " = :$targetEntityAttribute, ";
                } else {
                    unset($this->entityProperties[$key]);
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
                    if (isset($relation['joinColumn'])) {
                        $targetEntityJoinedColumn = $relation['joinColumn'];
                    }

                    if (isset($relation['attribute'])) {
                        $targetEntityAttribute = $relation['attribute'];
                    }

                    if (in_array($type, [
                        RelationType::MANY_TO_ONE,
                        RelationType::ONE_TO_ONE
                    ])) {
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
     * @param string $fields
     *
     * @return bool|\PDOStatement
     */
    public function prepareInsertSqlStatement($fields)
    {
        $sql = "INSERT INTO $this->table SET $fields";

        return $this->pdo->prepare($sql);
    }

    /**
     * @param $table
     * @param $fields
     * @param $entity
     *
     * @return \PDOStatement
     */
    public function prepareUpdateSqlStatement($fields, $entity)
    {
        $sql = "UPDATE $this->table
                SET $fields 
                WHERE id='{$entity->getId()}'";

        return $this->pdo->prepare($sql);
    }

    /**
     * @param $entity
     * @param $properties
     * @param \PDOStatement $stmt
     */
    public function hydrateFields($entity, &$stmt)
    {
        /* Hydrating the owner class */
        foreach ($this->entityProperties as $key => $property) {
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
                $this->hydrateRelation($property, $stmt, $entity);
            }
        }
    }

    /**
     * Function to hydrate the joined class(es) of manyToOne/oneToOne side
     *
     * @param $relations
     * @param \PDOStatement $stmt
     * @param $entity
     */
    public function hydrateRelation(&$relations, &$stmt, &$entity)
    {
        array_walk($relations, function($relation) use (&$stmt, &$entity){

            /** @var string $attribute */
            $attribute = $relation['attribute'];

            if (in_array($relation['type'], [
                RelationType::MANY_TO_ONE,
                RelationType::ONE_TO_ONE]
                )
                && isset($relation['joinColumn'])) {

                $getMethod = 'get' . ucfirst($attribute);
                $stmt->bindValue(
                    $attribute,
                    $entity->$getMethod()->getId()
                );
            }
        });
    }

    /**
     * @param array $relation
     * @param null $entity
     */
    public function hydrateManyToManyRelation(
        &$relation,
        &$entity = null
    )
    {
        /** @var string $targetClassTable */
        $targetClassTable = $relation['table'];

        /** @var string $joinTable */
        $joinTable = $relation['joinTable'];

        /** @var string $getMethod */
        $getMethod = 'get' . ucfirst($relation['attribute']);

        // E.g : user_id = :user
        $fields = $targetClassTable . '_id = :' . $targetClassTable . ', ';

        // E.g : game_id = :game
        $fields .= $this->table . '_id = :' . $this->table;

        if (empty($linkedEntities = $entity->$getMethod())) {
            return;
        }

        array_walk($linkedEntities,
            function($linkedEntity) use (
                $entity,
                $joinTable,
                $targetClassTable,
                $fields
            ) {

            $stmt = $this->pdo->prepare("INSERT INTO $joinTable SET $fields");
            $stmt->bindValue($targetClassTable, $linkedEntity->getId());
            $stmt->bindValue($this->table, $entity->getId());
            $stmt->execute();
        });
    }

    /**
     * @param string $entity
     * @param $operationType
     */
    public function insertUpdateOperation(
        $entity,
        $operationType
    )
    {
        /** @var string $fields */
        $fields = $this->getFields($entity);

        // Prepare the statement
        if ($operationType === self::PERSIST) {
            /** @var \PDOStatement $stmt */
            $stmt = $this->prepareInsertSqlStatement($fields);
        }

        if ($operationType === self::UPDATE) {
            /** @var \PDOStatement $stmt */
            $stmt = $this->prepareUpdateSqlStatement(
                $fields,
                $entity
            );
        }

        /* Hydrate the fields corresponding to the
         * fields defined with getFields method
         */
        $this->hydrateFields($entity, $stmt);

        /* Execute pdo statement */
        $stmt->execute();
    }

    /**
     * @param string $entity
     * @return array
     */
    public function getEntityProperties()
    {
        /** @var array $fields */
        $fields  = array_keys($this->classMetaData->fields);

        $properties = [];

        /** @var string $field */
        foreach ($fields as $key => $field) {
            $properties[$key]['attribute'] = $field;
        }

        /** @var string $field */
        foreach ($this->classMetaData->columns as $key => $column) {
            $properties[$key]['column'] = $column;
        }

        // Many To One relations

        $manyToOneRelations = $this->classMetaData->getRelations(
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
            $targetEntityManyToOneJoinedColumn = isset($data['joinColumn'])
                ? $data['joinColumn']
                : $defaultJoinedColumn;

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

            if ($this->classMetaData->isOwningSide($data)) {
                $properties['relation'][$field]['inversedBy'] = $data['inversedBy'];
            }
        }

        // One To Many relations
        /** @var array $oneToManyRelations */
        $oneToManyRelations = $this->classMetaData->getRelations(
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

        /** @var array $manyToManyRelations */
        $manyToManyRelations = $this->classMetaData->getRelations(
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
            }

            if ($this->classMetaData->isOwningSide($data)) {
                $properties['relation'][$field]['inversedBy'] = $data['inversedBy'];
                if (isset($data['joinTable'])) {
                    $properties['relation'][$field]['joinTable'] = $data['joinTable'];
                } else {
                    $properties['relation'][$field]['joinTable'] =
                        $targetEntityTable . '_' . $this->classMetaData->table;
                }
            }
        }

        // One To Many relations
        /** @var array $oneToOneRelations */
        $oneToOneRelations = $this->classMetaData->getRelations(
            RelationType::ONE_TO_ONE
        );

        /**
         * @var string $field
         * @var array $data
         */
        foreach ($oneToOneRelations as $field => $data) {

            /** @var ClassMetaData $targetEntityMetaData */
            $targetEntityMetaData = $this->getClassMetaData($data['target']);

            $targetEntityManyToOneJoinedColumn = null;

            if (isset($data['inversedBy'])) {
                /** @var string $defaultJoinedColumn */
                $defaultJoinedColumn = $targetEntityMetaData->table . '_' . 'id';

                /** @var string $targetEntityManyToOneJoinedColumn */
                $targetEntityManyToOneJoinedColumn = isset($data['joinColumn'])
                    ? $data['joinColumn']
                    : $defaultJoinedColumn;
            }

            // $properties will be filled with one to one relation info
            $this->setProperties(
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

        $this->removedUselessFieldForDb($properties);

        return $properties;
    }

    /**
     * @param array $properties
     */
    private function removedUselessFieldForDb(&$properties)
    {
        /** @var array $tablesColumns */
        $tablesColumns = $this->databaseMetaData->getTablesColumns();

        foreach ($properties as $key => $property) {
            /* checking that the column exists in table */
            /* Some class attributes may be used without any
               link with the database
            */
            if (is_array($property)
                && array_key_exists('column', $property)
                && !in_array(
                    $property['column'],
                    $tablesColumns[$this->classMetaData->table]
                )
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

        // Owning side should have a column name defined
        if (!empty($column)) {
            $properties['relation'][$key]['joinColumn'] = $column;
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
     */
    public function getEntityModel($className)
    {
        try {
            /** @var ClassMetaData $classMetaData */
            $classMetaData = $this->getClassMetaData($className);

            /** @var string $model */
            $model = $classMetaData->model;

            /** @var Model $model */
            return new $model(
                $this,
                $classMetaData,
                $this->session
            );

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