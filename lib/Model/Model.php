<?php

namespace Lib\Model;

use Lib\Model\Orm\ClassMetaData;
use Lib\Model\Orm\EntityManager;
use Lib\Model\Orm\QueryBuilder;
use Lib\Model\Relation\RelationType;
use Lib\Utils\Tools;

/**
 * Class EntityModel
 * @package Lib
 */
abstract class Model
{
    const SEARCH_ONE = 'One';
    const SEARCH_ALL = 'All';

    /** @var EntityManager $em */
    protected $em;

    /** @var string $class */
    protected $class;

    /** @var string $table */
    protected $table;

    /**
     * Do not change overtime, contrary to $table
     *
     * @var string $referenceTable
     */
    protected $referenceTable;

    /**
     * The entity properties
     *
     * @var array $properties
     */
    protected $properties;

    /**
     * The entity relations
     *
     * @var array $relations
     */
    protected $relations;

    /**
     * To avoid enless loops
     *
     * @var array $treatedRelations
     */
    protected $treatedRelations;

    /**
     * Model constructor.
     * @param EntityManager $entityManager
     * @param ClassMetaData $classMetaData
     * @throws \Exception
     */
    public function __construct(
        EntityManager $entityManager,
        ClassMetaData $classMetaData
    )
    {
        $this->em             = $entityManager;
        $this->class          = $classMetaData->class;
        $this->table          = $classMetaData->table;

        /** @var string referenceTable used for many to many associations */
        $this->referenceTable = $classMetaData->table;
        $this->properties     = $classMetaData->getEntityProperties();
        $this->relations      = $classMetaData->getFullEntityRelations();
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
     * @param array $criterias
     * @param bool $alreadyOneLevelOfHydration
     * @return object|null
     */
    public function findOneBy(
        $criterias = [],
        $alreadyOneLevelOfHydration = false
    )
    {
        $r = $this->findByCriteria(
            $criterias,
            self::SEARCH_ONE,
            $alreadyOneLevelOfHydration
        );

        if (!$r) {
            return null;
        }

        return $r;
    }

    /**
     * @param array $criterias
     * @param bool $alreadyOneLevelOfHydration
     * @return array|mixed
     */
    public function findBy(
        $criterias = [],
        $alreadyOneLevelOfHydration = false
    )
    {
        $r = $this->findByCriteria(
            $criterias,
            self::SEARCH_ALL,
            $alreadyOneLevelOfHydration
        );

        if (!$r) {
            return [];
        }

        return $r;
    }

    /**
     * @param array $criterias
     * @param string $fetchType
     * @param bool $alreadyOneLevelOfHydration
     * @return array|mixed|object
     */
    public function findByCriteria(
        $criterias = [],
        $fetchType = self::SEARCH_ALL,
        $alreadyOneLevelOfHydration = false
    )
    {
        $relations = null;

        foreach ($this->properties as $key => $property) {
            /* If in the given criteria(s), a key corresponds to an attribute (joined class) */
            /* Replacing the attribute by the corresponding table column name */
            /* E.g : giving owner leads to replace owner by owner_id */
            if ($key === 'relation') {
                $relations = $property;
                foreach ($criterias as $prop => $value) {
                    foreach ($relations as $relation) {
                        if ($prop === $relation['attribute']) {
                            unset($criterias[$prop]);
                            $criterias[$relation['joinColumn']] = $value;
                        }
                    }
                }
            }
        }

        $firstKey = array_keys($criterias)[0];
        $value = $criterias[$firstKey];

        // a.src as src, a.id as id...
        /** @var string $fields */
        $fields = $this->em->transformEntityColumnsNameToEntityAttributes(
            $this->properties
        );

        // Complete if has many to one relations
        // E.g : dummy_id as dummy
        $this->em->addEntityRelationAttribute(
            $this->relations,
            $fields
        );

        /** @var QueryBuilder $query */
        $query = $this
            ->em->createQueryBuilder($this->class)
            ->select($fields)
            ->from($this->table)
            ->where("$firstKey = :firstKey")
            ->setParameter('firstKey', $value);

        if (count($criterias) > 1) {
            foreach ($criterias as $key => $criteria) {
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

        // The deep of recursive hydration
//        if ($this->i === 1) {
//
//        }

//        $this->i++;

        /* relation may not exist */
        /* alreadyOneLevelOfHydration => to avoid endless hydration between joined classes */
        if (!empty($relations) && !$alreadyOneLevelOfHydration && $results) {
            $this->handleRelations($results, $this->properties, $relations);
        }

        if ($fetchType === self::SEARCH_ONE && is_array($results)) {
            return $results[0];
        }

        return $results;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function findAll()
    {
        /** @var string $fields */
        $fields = $this->em->transformEntityColumnsNameToEntityAttributes(
            $this->properties
        );

        // Complete select statement with relation attributes
        $this->em->addEntityRelationAttribute(
            $this->relations,
            $fields
        );

        /** @var array $results */
        $results = $this->executeFindStatement($fields);

        if (!$results) {
            return [];
        }

        // Fill in relation attributes of this entity
        if (!empty($this->relations)) {
            $this->handleRelations(
                $results,
                $this->properties,
                $this->relations
            );
        }

        return $results;
    }

    /**
     * @param object|array $results
     * @param array $properties
     * @param $relations
     */
    private function handleRelations($results, $properties, $relations)
    {
        /* Find find or find one by */
        if (!is_array($results)) {
            $results = [$results];
        }

        /**
         * @var string $key
         * @var array $relation
         */
        foreach ($this->relations as $key => $relation) {

            /* To move from one relation to another, whatever the type */
            if (!isset($this->treatedRelations[$key])) {

                $this->treatedRelations[$key] = $key;

                /** @var string $type */
                $type = $relation['type'];

                /* Changing table and class for joined objects hydration */
                /* See findBy [ONE_TO_MANY] and find [MANY_TO_ONE] next */
                $this->em->setClassMetaData($relation['class']);

                /** @var string $joinedObjectAttribute */
                $joinedObjectAttribute = $relation['attribute'];

                /** @var string $getMethod */
                $getMethod = 'get'.ucfirst($joinedObjectAttribute);

                /* attribute on the one to many  side => array E.g addImage */
                /* attribute on the many to one side => object E.g setUser */
                /* attribute on the many to many side => array E.g addImg */
                if (in_array($type, [
                    RelationType::ONE_TO_MANY,
                    RelationType::MANY_TO_MANY])) {

                    $prefix = 'add';
                } else {
                    $prefix = 'set';
                }

                /** @var string $setMethod */
                $setMethod = $prefix . ucfirst(Tools::TransformEndOfWord($joinedObjectAttribute));

                /* Needs to update for the tablesColumns checking */
                $this->class = $relation['class'];
                $this->table = $relation['table'];
                $this->properties = $this->em->getClassMetaData()->getEntityProperties();

                // OneToMany Side
                if ($type === RelationType::ONE_TO_MANY) {

                    /* Common to all looped results below ... */
                    $rightSideTargetColumn = $relation['mappedBy'];

                    foreach ($results as $result) {
                        /* See comment in findByCriteria => owner -> owner_id */
                        /* Fetching the corresponding entities */
                        $rightSideResults = $this->findBy([
                            $rightSideTargetColumn => $result->getId()
                        ], true);

                        /* Hydration of the one-to-many side relation attribute */
                        $result->$setMethod($rightSideResults);
                    }
                }

                // ManyToOne side
                if ($type === RelationType::MANY_TO_ONE) {
                    /* Hydrating the object corresponding to the join column */
                    foreach ($results as $result) {
                        $joinedObjectId = $result->$getMethod();
                        $joinedObject = $this->find(
                            $joinedObjectId,
                            true
                        );
                        /* Hydration */
                        $result->$setMethod($joinedObject);
                    }
                }

                if ($type === RelationType::MANY_TO_MANY) {

                    /** @var string $relationTableName */
                    $relationTableName = $relation['table'];

                    /* E.g user_game */
                    $this->table = $relation['joinTable'];

                    // E.G : game.id
                    $linkedEntityReferenceField = $relationTableName . '.id';

                    // E.G : user_game.game_id
                    $joinTableTargetEntityField = $this->table . '.' . $relationTableName . '_id';

                    // E.G : user_game.user_id
                    $joinTableSourceEntityField = $this->table . '.' . $this->referenceTable . '_id';

                    /** @var string $fields */
                    $fields = $this->em->transformEntityColumnsNameToEntityAttributes(
                        $this->properties
                    );

                    array_walk($results, function(&$result) use(
                        $fields,
                        $relationTableName,
                        $joinTableTargetEntityField,
                        $joinTableSourceEntityField,
                        $linkedEntityReferenceField,
                        $setMethod
                    ) {
                        $query = $this->em->createQueryBuilder($this->class)
                            ->select($fields)
                            ->from($relationTableName)
                            ->join(
                                $this->table,
                                'ON',
                                "$joinTableTargetEntityField = $linkedEntityReferenceField"
                            )
                            ->where("$joinTableSourceEntityField = :id")
                            ->setParameter('id', $result->getId());

                        $joinedEntities = $query->getQuery()->getResult();

                        if (!empty($joinedEntities)) {
                            foreach ($joinedEntities as $joinedEntity) {
                                $result->$setMethod($joinedEntity);
                            }
                        }
                    });
                }

                /* Recursive call until no more relation to process */
                $this->handleRelations($results, $properties, $relations);
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
            ->em->createQueryBuilder($this->class)
            ->select($str)
            ->from($this->table);

        if (!is_null($column) && !is_null($id)) {
            $query->where("$column = :param_$column")
                  ->setParameter("param_$column", $id);
        }

        return $query->getQuery()->getResult() ?: [];
    }
}