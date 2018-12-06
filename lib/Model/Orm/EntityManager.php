<?php

namespace Lib\Model\Orm;

use Lib\Model\Connection\PDOFactory;
use Lib\Model\Exception\Model\ClassMetaDataException;
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

    /** @var RelationManager $relationManager */
    private $relationManager;

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
     */
    public function __construct(
        PDOFactory $PDOFactory,
        ClassMetaDataFactory $classMetaDataFactory,
        DatabaseMetaData $databaseMetaData
    )
    {
        $this->pdo                   = $PDOFactory::getConnexion();
        $this->classMetaDataFactory  = $classMetaDataFactory;
        $this->databaseMetaData      = $databaseMetaData;
        $this->relationManager       = new RelationManager($this);
    }

    /**
     * @param object $entity
     *
     * @return void
     * @throws ClassMetaDataException
     */
    public function persist($entity)
    {
        // Avoid persisting twice this entity
        if (!in_array($entity, $this->persistedEntities)) {

            $this->setClassMetaData($entity);

            try {
                /** @var string|null $entityPrimaryKey */
                $entityPrimaryKey = $this->classMetaData->getPrimaryKey();
            } catch (ClassMetaDataException $classMetaDataException) {
                throw $classMetaDataException;
            }

            /** @var string $fields */
            $fields = $this->getFields($entity);

            $qb = (new QueryBuilder($this))
                ->insertInto($this->table)
                ->set($fields);

            /**
             * Set qb parameters
             */
            $this->hydrateFields($entity, $qb);

            /* Execute pdo statement */
            $qb->getQuery()->execute();

            $lastInsertId = $this->pdo->lastInsertId();

            $setMethod = 'set' . ucfirst($entityPrimaryKey);

            // Hydrate entity primary key
            if (method_exists($entity, $setMethod)) {
                $entity->$setMethod($lastInsertId);
            }

            $this->persistedEntities[] = $entity;

            try {
                $this->relationManager->handleRelations($entity);
            } catch (ClassMetaDataException $classMetaDataException) {
                throw $classMetaDataException;
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
        $this->setClassMetaData($entity);

        /** @var string $fields */
        $fields = $this->getFields($entity);

        $qb = (new QueryBuilder($this))
            ->update($this->table)
            ->set($fields);

        /** @var array $relations */
        $manyToManyRelations = $this->classMetaData->getFullEntityRelations(RelationType::MANY_TO_MANY);

        array_walk($manyToManyRelations, function($relation) use (&$entity) {
            $this->hydrateManyToManyRelation(
                $relation,
                $entity
            );
        });
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
        // See insertUpdateOperation() for this->entity
        $fields = '';

        $properties = $this->classMetaData->getEntityProperties();
        $this->removedUselessFieldForDb($properties);

        array_walk(
            $properties,
            [$this, 'concat'],
            [
                'fields' => &$fields,
                'tablesColumns' => $this->databaseMetaData->getTablesColumns(),
                'table' => $this->getClassMetaData($entity)->table
            ]
        );

        return rtrim($fields, ', ');
    }

    /**
     * @param $property
     * @param string $key
     * @param array $args
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function concat($property, $key, $args)
    {
        if (!is_array($property)) {
            throw new \InvalidArgumentException(
                sprintf("Invalid property given for %s", $key)
            );
        }

        if ($key !== 'relation') {

            $targetEntityTableColumn = $property['column'];
            $targetEntityAttribute = $property['attribute'];

            // Checking whether the target table column exists or not
            // Indeed, the target entity attribute many not be in database
            if (in_array($targetEntityTableColumn,
                $args['tablesColumns'][$args['table']])) {
                $args['fields'] .= $targetEntityTableColumn . " = :$targetEntityAttribute, ";
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
                    if (in_array($targetEntityJoinedColumn, $args['tablesColumns'][$args['table']])) {
                        $args['fields'] .= $targetEntityJoinedColumn . " = :$targetEntityAttribute, ";
                    }
                }
            }
        }
    }

    /**
     * @param string $fields
     * @param object $entity
     *
     * @return \PDOStatement
     */
    public function prepareUpdateSqlStatement(&$fields, &$entity)
    {
        $sql = "UPDATE $this->table
                SET $fields 
                WHERE id='{$entity->getId()}'";

        return $this->pdo->prepare($sql);
    }

    /**
     * @param $entity
     * @param QueryBuilder $qb
     */
    public function hydrateFields($entity, &$qb)
    {
        /* Hydrating the owner class */
        /** @var array $properties */
        $properties = $this->classMetaData->getEntityProperties();
        $this->removedUselessFieldForDb($properties);

        foreach ($properties as $key => $property) {
            if (is_array($property) && $key !== 'relation') {
                /** @var string $attribute */
                $attribute = $property['attribute'];
                $method = 'get' . ucfirst($attribute);

                if (is_array($value = $entity->$method())) {
                    $value = serialize($value);
                }

                $qb->setParameter($attribute, $value);
            }

            if ($key === 'relation') {
                $this->hydrateRelation(
                    $property,
                    $entity,
                    $qb
                );
            }
        }
    }

    /**
     * Function to hydrate the joined class(es) of manyToOne/oneToOne side
     *
     * @param $relations
     * @param $entity
     * @param QueryBuilder $qb
     */
    public function hydrateRelation(&$relations, &$entity, &$qb)
    {
        array_walk($relations, function($relation) use (&$entity, &$qb){

            /** @var string $attribute */
            $attribute = $relation['attribute'];

            if (in_array($relation['type'], [
                RelationType::MANY_TO_ONE,
                RelationType::ONE_TO_ONE]
                )
                && isset($relation['joinColumn'])) {

                $getMethod = 'get' . ucfirst($attribute);
                $qb->setParameter(
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
     * Function to remove useless entity fields
     * May be defined in the yaml file but not exist in db
     * E.g : extra fields such as file
     *
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
                && isset($property['column'])
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
     * Function to build the select statement string
     *
     * Do not take into account the entity relations
     *
     * @param array $properties
     *
     * @return string
     */
    public function transformEntityColumnsNameToEntityAttributes(&$properties)
    {
        $fields = '';

        array_walk($properties, function($property, $key) use (&$fields) {
            if ($key !== 'relation') {
                $fields .=  $property['column']
                    . ' AS ' . $property['attribute'] . ', ';
            }
        });

        return rtrim($fields, ', ');
    }

    /**
     * Function to complete the 'field' string for the select statement
     *
     * For many to one relations
     *
     * @param array $relations
     * @param string $fields
     *
     * @return string
     */
    public function addEntityRelationAttribute(
        &$relations,
        &$fields
    )
    {
        array_walk($relations, function ($relation) use (&$fields) {
            // Check as no column on the OneToMany side
            if ($relation['type'] === RelationType::MANY_TO_ONE) {
                $fields .= ', ' . $relation['joinColumn']
                    . ' AS ' . $relation['attribute']
                    . ', ';
            }
        });

        /** @var string $fields */
        $fields = rtrim($fields, ', ');
    }

    /**
     * Function to return the model related to an entity
     * src/Model
     *
     * @param string|object $className
     *
     * @return Model
     */
    public function getEntityModel($className)
    {
        if (is_object($className)) {
            // Get path to the entity class
            $className = get_class($className);
        }

        /** @var ClassMetaData $classMetaData */
        $classMetaData = $this->getClassMetaData($className);

        /** @var string $model */
        $model = $classMetaData->model;

        /** @var Model $model */
        return new $model($this, $classMetaData);
    }

    /**
     * @param null $class
     * @param null $table
     * @return QueryBuilder
     */
    public function createQueryBuilder($class = null, $table = null)
    {
        return new QueryBuilder($this, $class, $table);
    }

    /**
     * @return DatabaseMetaData
     */
    public function getDatabaseMetaData()
    {
        return $this->databaseMetaData;
    }

    /**
     * @param object $entity
     *
     * @return $this
     * @throws ClassMetaDataException
     */
    public function handleOneToOneRelations(&$entity)
    {
        if (!empty($relations = $this->classMetaData->getFullEntityRelations(RelationType::ONE_TO_ONE))) {
            try {
                $this->cascadePersist(
                    $relations,
                    $entity,
                    RelationType::ONE_TO_ONE
                );
            } catch (ClassMetaDataException $classMetaDataException) {
                throw $classMetaDataException;
            }
        }

        return $this;
    }

    /**
     * @param object $entity
     *
     * @return $this
     * @throws ClassMetaDataException
     */
    public function handleOneToManyRelations(&$entity)
    {
        if (!empty($relations = $this->classMetaData->getFullEntityRelations(RelationType::ONE_TO_MANY))) {
            try {
                $this->cascadePersist(
                    $relations,
                    $entity,
                    RelationType::ONE_TO_MANY
                );
            } catch (ClassMetaDataException $classMetaDataException) {
                throw $classMetaDataException;
            }
        }

        return $this;
    }

    /**
     * @param object $entity
     */
    public function handleManyToManyRelations(&$entity)
    {
        $this->setClassMetaData($entity);

        if (!empty($manyToManyRelations = $this->classMetaData->getFullEntityRelations(RelationType::MANY_TO_MANY))) {
            // Only the owning side of the relation hydrates
            // a many-to-many association
            array_walk(
                $manyToManyRelations,
                function ($relation) use ($entity) {
                    if (isset($relation['inversedBy'])) {
                        $this->hydrateManyToManyRelation($relation, $entity);
                    }
                }
            );
        }
    }

    /**
     * Function to enable an entity to persist linked entities
     * if in its relation attributes, the cascade key is defined
     * and has the value persist, among others
     *
     * @param array $relations
     * @param object $entity
     * @param string $type
     *
     * @return void
     * @throws ClassMetaDataException
     */
    public function cascadePersist($relations, &$entity, $type)
    {
        foreach ($relations as $attribute => $data) {
            if ($this->classMetaData->hasCascadePersist($data)) {
                $getMethod = 'get' . ucfirst($attribute);
                if ($type === RelationType::ONE_TO_ONE) {
                    try {
                        $this->persist($entity->$getMethod());
                    } catch (ClassMetaDataException $classMetaDataException) {
                        throw $classMetaDataException;
                    }
                    continue;
                }

                if ($type === RelationType::ONE_TO_MANY) {
                    foreach ($entity->$getMethod() as $targetEntity) {
                        try {
                            $this->persist($targetEntity);
                        } catch (ClassMetaDataException $classMetaDataException) {
                            throw $classMetaDataException;
                        }
                    }
                }
            }
        }
    }

    /**
     * @return \PDO
     */
    public function getConnection()
    {
        return $this->pdo;
    }

    /**
     * Function to return a class meta data
     *
     * @param string|object|array|null $entity
     * @return ClassMetaData
     */
    public function getClassMetaData($entity = null)
    {
        if (is_null($entity)) {
            return $this->classMetaData;
        }

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
     * Function to set the class meta data corresponding to the entity
     *
     * @param object $entity
     */
    public function setClassMetaData(&$entity)
    {
        /** @var ClassMetaData $classMetaData */
        $this->classMetaData    = $this->getClassMetaData($entity);
        $this->table            = $this->classMetaData->table;
    }
}