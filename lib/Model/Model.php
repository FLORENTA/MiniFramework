<?php

namespace Lib\Model;

use Lib\Model\Orm\ClassMetaData;
use Lib\Model\Orm\EntityManager;
use Lib\Model\Orm\QueryBuilder;
use Lib\Model\Relation\RelationType;
use Lib\Utils\Tools;

/**
 * Class Model
 * @package Lib
 */
abstract class Model
{
    const SEARCH_ONE = 'One';
    const SEARCH_ALL = 'All';
    const ON         = 'ON';

    /** @var EntityManager $em */
    protected $em;

    /** @var string $class */
    protected $class;

    /** @var string $table */
    protected $table;

    /** @var array $treatedRelations */
    protected $treatedRelations = [];

    /** @var array $linkedEntityTreatedRelations */
    protected $linkedEntityTreatedRelations = [];

    /** @var ClassMetaData $classMetaData */
    protected $classMetaData;

    /**
     * Model constructor.
     * @param EntityManager $entityManager
     * @param ClassMetaData $classMetaData
     */
    public function __construct(
        EntityManager $entityManager,
        ClassMetaData $classMetaData
    )
    {
        $this->em             = $entityManager;
        $this->classMetaData  = $classMetaData;
    }

    /**
     * @return null|string
     */
    public function getClass()
    {
        return $this->classMetaData->class;
    }

    /**
     * @return null|string
     */
    public function getTable()
    {
        return $this->getClassMetaData()->table;
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->getClassMetaData()->getEntityProperties();
    }

    /**
     * @return array
     */
    public function getRelations()
    {
        return $this->getClassMetaData()->getFullEntityRelations();
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    /**
     * @return ClassMetaData
     */
    public function getClassMetaData()
    {
        return $this->classMetaData;
    }

    /**
     * @param integer $id
     * @param bool $alreadyOneLevelOfHydration
     * @return object
     */
    public function find($id, $alreadyOneLevelOfHydration = false)
    {
        $r = $this->findByCriteria(
            ['id' => $id],
            self::SEARCH_ONE,
            $alreadyOneLevelOfHydration
        );

        if (!$r) {
            return null;
        }

        return $r;
    }

    /**
     * @param array $data
     * @param bool $alreadyOneLevelOfHydration
     * @return object|null
     */
    public function findOneBy(
        $data = [],
        $alreadyOneLevelOfHydration = false
    )
    {
        $r = $this->findByCriteria(
            $data,
            self::SEARCH_ONE,
            $alreadyOneLevelOfHydration
        );

        if (!$r) {
            return null;
        }

        return $r;
    }

    /**
     * @param array $data
     * @param bool $alreadyOneLevelOfHydration
     * @return array
     */
    public function findBy(
        $data = [],
        $alreadyOneLevelOfHydration = false
    )
    {
        $r = $this->findByCriteria(
            $data,
            self::SEARCH_ALL,
            $alreadyOneLevelOfHydration
        );

        if (!$r) {
            return [];
        }

        return $r;
    }

    /**
     * @param array $data
     * @param string $fetchType
     * @param bool $alreadyOneLevelOfHydration
     * @return array|mixed|object
     */
    public function findByCriteria(
        $data = [],
        $fetchType = self::SEARCH_ALL,
        $alreadyOneLevelOfHydration = false
    )
    {
        $relations = null;

        foreach ($this->getProperties() as $key => $property) {
            /* If in the given criteria(s), a key corresponds to an attribute (joined class) */
            /* Replacing the attribute by the corresponding table column name */
            /* E.g : giving owner leads to replace owner by owner_id */
            if ($key === 'relation') {
                $relations = $property;
                foreach ($data as $prop => $value) {
                    foreach ($relations as $relation) {
                        if ($prop === $relation['attribute']) {
                            unset($data[$prop]);
                            $data[$relation['joinColumn']] = $value;
                        }
                    }
                }
            }
        }

        $firstKey = array_keys($data)[0];
        $value = $data[$firstKey];

        /** @var array $properties */
        $properties = $this->getProperties();

        /** @var array $r */
        $r = $this->getRelations();

        // a.src as src, a.id as id...
        /** @var string $fields */
        $fields = $this->em->transformEntityColumnsNameToEntityAttributes($properties);

        // Complete if has many to one relations
        // E.g : dummy_id as dummy
        $this->em->addEntityRelationAttribute($r, $fields);

        /** @var QueryBuilder $query */
        $query = $this
            ->createQueryBuilder()
            ->select($fields)
            ->where("$firstKey = :firstKey")
            ->setParameter('firstKey', $value);

        if (count($data) > 1) {
            foreach ($data as $key => $criteria) {
                if ($key !== $firstKey) {
                    $query->andWhere("$key = :param_$key")
                        ->setParameter("param_$key", "$criteria");
                }
            }
        }

        if ($fetchType === self::SEARCH_ONE) {
            $query->setMaxResults(1);
            /** @var object $results */
            $results = $query->getQuery()->getSingleResult();
        } else {
            /** @var array $results */
            $results = $query->getQuery()->getResult();
        }

        /* relation may not exist */
        /* alreadyOneLevelOfHydration => to avoid endless hydration between joined classes */
        if (!empty($relations) && !$alreadyOneLevelOfHydration && $results) {
            $this->handleRelations($results, $relations);
        }

        if ($fetchType === self::SEARCH_ONE && is_array($results)) {
            return $results[0];
        }

        return $results;
    }

    /**
     * @return array
     */
    public function findAll()
    {
        /** @var array $properties */
        $properties = $this->getProperties();

        /** @var array $relations */
        $relations = $this->getRelations();

        /** @var string $fields */
        $fields = $this->em->transformEntityColumnsNameToEntityAttributes($properties);

        // Complete select statement with relation attributes
        $this->em->addEntityRelationAttribute($relations, $fields);

        /** @var array $results */
        $results = $this->executeFindStatement($fields);

        if (!$results) {
            return [];
        }

        // Fill in relation attributes of this entity
        if (!empty($this->getRelations())) {
            $this->handleRelations($results, $relations);
        }

        return $results;
    }

    /**
     * @param object|array $results
     * @param $relations
     */
    public function handleRelations(&$results, &$relations, $originModelEntity = null)
    {
        if (!is_array($results)) {
            $results = [$results];
        }

        /**
         * @var string $key
         * @var array $relation
         */
        foreach ($relations as $key => $relation) {

            /* To move from one relation to another, whatever the type */
            if (!isset($this->treatedRelations[$key]) && $originModelEntity !== $relation['class']) {

                $this->treatedRelations[$key] = $key;

                /** @var Model $targetClassModel */
                $targetClassModel = $this->em->getEntityModel($relation['class']);

                /** @var string $joinedObjectAttribute */
                $joinedObjectAttribute = $relation['attribute'];

                /** @var array $linkedEntityRelations */
                $linkedEntityRelations = $targetClassModel->getRelations();

                /** @var int $nbRelations */
                $nbRelations = count($linkedEntityRelations);

                $joinedEntities = [];

                /**
                 * @var string $k
                 * @var array $linkedEntityRelation
                 */
                foreach ($linkedEntityRelations as $k => $linkedEntityRelation) {

                    if (!in_array($k, $this->linkedEntityTreatedRelations)) {
                        $this->linkedEntityTreatedRelations[$k] = $k;

                        /** @var string $type */
                        $type = $linkedEntityRelation['type'];

                        if ($linkedEntityRelation['class'] === $this->getClass()) {

                            // ManyToOne side
                            if ($type === RelationType::MANY_TO_ONE) {

                                $joinColumn = $linkedEntityRelation['joinColumn'];
                                $primaryKey = $this->getClassMetaData()->getPrimaryKey();

                                // The primary key of the current class. E.g : getId
                                $getMethod = 'get' . ucfirst($primaryKey);
                                // The many to one side 'sets' this class. E.g. : setDummy
                                $setMethod = 'set' . ucfirst($k);
                                // The one to many side 'adds' this class. E.g : addImages
                                $addMethod = 'add' . ucfirst(Tools::TransformEndOfWord($joinedObjectAttribute));

                                foreach ($results as &$result) {
                                    /** @var array $linkedEntities E.g : array of images */
                                    $manyToOneSideJoinedEntities =
                                        $targetClassModel
                                            ->createQueryBuilder($targetClassModel->getClass())
                                            ->from($targetClassModel->getTable())
                                            ->where("$joinColumn = :value")
                                            ->setParameter("value", $result->$getMethod())
                                            ->getQuery()->getResult();

                                    foreach ($manyToOneSideJoinedEntities as &$joinedEntity) {
                                        $id = $joinedEntity->{'get' . ucfirst($targetClassModel->getClassMetaData()->getPrimaryKey())}();
                                        $joinedEntities[$id] = $joinedEntity;
                                        if (method_exists($joinedEntity, $setMethod)) {
                                            $joinedEntity->$setMethod($result);
                                        }

                                        if (method_exists($result, $addMethod)) {
                                            $result->$addMethod($joinedEntity);
                                        }
                                    }
                                }
                            }

                            if ($type === RelationType::MANY_TO_MANY && isset($linkedEntityRelation['inversedBy'])) {

                                $sourceEntityAddMethod = 'add' . ucfirst(Tools::TransformEndOfWord($linkedEntityRelation['inversedBy']));
                                $targetEntityAddMethod = 'add' . ucfirst(Tools::TransformEndOfWord($linkedEntityRelation['attribute']));

                                /** @var string $relationTableName */
                                $relationTableName = $linkedEntityRelation['table'];

                                /* E.g user_game */
                                $joinTable = $linkedEntityRelation['joinTable'];

                                // E.G : game.id
                                $linkedEntityReferenceField = $targetClassModel->getTable() . '.id';

                                // E.G : user_game.game_id
                                $joinTableSourceEntityField = $joinTable . '.' . $relationTableName . '_id';

                                // E.G : user_game.user_id
                                $joinTableTargetEntityField = $joinTable . '.' . $targetClassModel->getTable() . '_id';

                                /** @var array $properties */
                                $properties = $targetClassModel->getProperties();

                                /** @var string $fields */
                                $fields = $this->em->transformEntityColumnsNameToEntityAttributes(
                                    $properties
                                );

                                foreach ($results as &$result) {
                                    $query = $this
                                        ->createQueryBuilder($targetClassModel->getClass())
                                        ->select($fields)
                                        ->from($targetClassModel->getTable())
                                        ->join($joinTable, self::ON, "$joinTableTargetEntityField = $linkedEntityReferenceField")
                                        ->where("$joinTableSourceEntityField = :id")
                                        ->setParameter('id', $result->getId());

                                    /** @var array $manyToManySideJoinedEntities */
                                    $manyToManySideJoinedEntities = $query->getQuery()->getResult();

                                    foreach ($manyToManySideJoinedEntities as &$joinedEntity) {

                                        $id = $joinedEntity->{'get' . ucfirst($targetClassModel->getClassMetaData()->getPrimaryKey())}();
                                        if (in_array(
                                            $id,
                                            array_values(array_keys($joinedEntities)))
                                        ) {
                                            if (method_exists($joinedEntity, $targetEntityAddMethod)) {
                                                $joinedEntities[$id]->$targetEntityAddMethod($result);
                                            }

                                            if (method_exists($result, $sourceEntityAddMethod)) {
                                                $result->$sourceEntityAddMethod($joinedEntities[$id]);
                                            }
                                        } else {
                                            $joinedEntities[$id] = $joinedEntity;
                                            if (isset($linkedEntityRelation['inversedBy'])) {
                                                if (method_exists($joinedEntity, $targetEntityAddMethod)) {
                                                    $joinedEntity->$targetEntityAddMethod($result);
                                                }

                                                if (method_exists($result, $sourceEntityAddMethod)) {
                                                    $result->$sourceEntityAddMethod($joinedEntity);
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            // If all relations have been treated, let's handle the relations
                            // of all the many to one side entities
                            // originEntityModel as third argument to prevent from treating  again
                            // the already treated entities
                            if (!empty($targetClassModel->getRelations()) && $nbRelations === count($this->linkedEntityTreatedRelations)) {
                                $targetClassModel->handleRelations($joinedEntities, $linkedEntityRelations, $this->getClass());
                            }
                        }
                    }
                }

                /* Recursive call until no more relation to process */
                $this->handleRelations($results, $relations);
            }
        }
    }

    /**
     * @param $str
     * @param null $column
     * @param null $id
     * @return array
     */
    public function executeFindStatement(
        $str,
        $column = null,
        $id = null
    )
    {
        $query = $this
            ->em->createQueryBuilder($this->getClass())
            ->select($str)
            ->from($this->getTable());

        if (!is_null($column) && !is_null($id)) {
            $query->where("$column = :param_$column")
                ->setParameter("param_$column", $id);
        }

        return $query->getQuery()->getResult() ?: [];
    }

    /**
     * @param null $class
     * @param null $table
     * @return QueryBuilder
     */
    public function createQueryBuilder($class = null, $table = null)
    {
        // If class not defined, by default,
        // let's take the current model corresponding entity
        $class = $class ?: $this->getClass();
        // idem for table
        $table = $table ?: $this->getTable();

        return $this->em->createQueryBuilder($class, $table);
    }
}