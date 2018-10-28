<?php

namespace Classes\Model;

use Classes\Http\Session;
use Classes\Model\Orm\ClassMetaData;
use Classes\Model\Orm\EntityManager;
use Classes\Model\Relation\RelationType;

/**
 * Class EntityModel
 * @package Classes
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

    /** @var string $referenceTable */
    protected $referenceTable;

    /** @var  array $properties*/
    protected $properties;

    /** @var string $fields */
    protected $fields;

    /** @var array $treatedRelations */
    protected $treatedRelations;

    /** @var Session $session */
    protected $session;

    /**
     * Model constructor.
     * @param EntityManager $entityManager
     * @param Session $session
     * @param ClassMetaData $classMetaData
     * @throws \Exception
     */
    public function __construct(
        EntityManager $entityManager,
        ClassMetaData $classMetaData,
        Session $session
    )
    {
        $this->em             = $entityManager;
        $this->session        = $session;
        $this->class          = $classMetaData->class;
        $this->table          = $classMetaData->table;

        /** @var string referenceTable used for many to many associations */
        $this->referenceTable = $classMetaData->table;

        $this->properties     = $entityManager->getEntityProperties($this->class);
        $this->fields         = $entityManager->transformEntityColumnsNameToEntityAttributes($this->properties);
    }

    /**
     * @param integer $id
     * @param bool $alreadyOneLevelOfHydration
     * @return object
     */
    public function find($id, $alreadyOneLevelOfHydration = false)
    {
        return $this->findByCriteria(
            ['id' => $id],
            self::SEARCH_ONE,
            $alreadyOneLevelOfHydration
        );
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
        return $this->findByCriteria(
            $criterias,
            self::SEARCH_ONE,
            $alreadyOneLevelOfHydration
        );
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
        return $this->findByCriteria(
            $criterias,
            self::SEARCH_ALL,
            $alreadyOneLevelOfHydration
        );
    }

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
                            $criterias[$relation['column']] = $value;
                        }
                    }
                }
            }
        }

        $firstKey = array_keys($criterias)[0];
        $value = $criterias[$firstKey];

        $query = $this->em->createQueryBuilder($this->class)
            ->select($this->fields)
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
            $results = $query->getQuery()->getSingleResult();
        } else {
            $results = $query->getQuery()->getResult();
        }

//        if ($this->i === 1) { // hydration step
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
        try {
            $results = $this->executeFindStatement($this->fields);

            /* relation may not exist */
            if (array_key_exists('relation', $this->properties)) {
                $relations = $this->properties['relation'];
                $this->handleRelations($results, $this->properties, $relations);
            }

            return $results;
        } catch (\Exception $exception) {
            throw $exception;
        }
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
        foreach ($relations as $key => $relation) {

            /* To move from one relation to another, whatever the type */
            if (!isset($this->treatedRelations[$key])) {

                $this->treatedRelations[$key] = $key;

                /** @var string $type */
                $type = $relation['type'];

                /* Changing table and class for joined objects hydration */
                /* See findBy [ONE_TO_MANY] and find [MANY_TO_ONE] next */
                $this->class = $relation['class'];

                /** @var string $joinedObjectAttribute */
                $joinedObjectAttribute = $relation['attribute'];

                $getMethod = 'get'.ucfirst($joinedObjectAttribute);

                /* attribute on the one to many  side => array E.g setGames */
                /* attribute on the many to one side => object E.g setUser */
                /* attribute on the many to many side => array E.g setParticipations */
                $setMethod = 'set' . ucfirst($joinedObjectAttribute);

                if (in_array($type, [
                    RelationType::ONE_TO_MANY,
                    RelationType::MANY_TO_ONE
                ])) {

                    /* Needs to update for the tablesColumns checking */
                    $this->table = $relation['table'];
                    $this->properties = $this->em->getEntityProperties($this->class);
                    $this->fields = $this->em->transformEntityColumnsNameToEntityAttributes($this->properties);
                }

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
                    $linkedEntityReferenceField = "$relationTableName.id";

                    // E.G : user_game.game_id
                    $joinTableTargetEntityField = "$this->table.$relationTableName" . "_id";

                    // E.G : user_game.user_id
                    $joinTableSourceEntityField = "$this->table.$this->referenceTable" . "_id";

                    foreach ($results as $result) {

                        $query = $this->em->createQueryBuilder($this->class)
                            ->select($this->fields)
                            ->from($relationTableName )
                            ->join(
                                $this->table,
                                'ON',
                                "$joinTableTargetEntityField = $linkedEntityReferenceField"
                            )
                            ->where("$joinTableSourceEntityField = :id")
                            ->setParameter('id', $result->getId());

                        $joinedEntities = $query->getQuery()->getResult();

                        if (!empty($joinedEntities)) {
                            $result->$setMethod($joinedEntities);
                        }
                    }
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
        $query = $this->em->createQueryBuilder($this->class)
            ->select($str)
            ->from($this->table);

        if (!is_null($column) && !is_null($id)) {
            $query->where("$column = :param_$column")
                  ->setParameter("param_$column", $id);
        }

        return $query->getQuery()->getResult();
    }
}