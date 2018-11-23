<?php

namespace Lib\Model\Orm;

use Lib\Model\Exception\Model\ClassMetaDataException;
use Lib\Model\Relation\RelationType;

/**
 * Class RelationManager
 * @package Lib\Model\Orm
 */
class RelationManager
{
    /** @var EntityManager $em */
    private $em;

    /**
     * RelationManager constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * @param $entity
     * @throws ClassMetaDataException
     */
    public function handleRelations(&$entity)
    {
        try {
            $this
                ->handleOneToOneRelations($entity)
                ->handleOneToManyRelations($entity)
                ->handleManyToManyRelations($entity);
        } catch (ClassMetaDataException $classMetaDataException) {
            throw $classMetaDataException;
        }
    }

    /**
     * @param object $entity
     *
     * @return $this
     * @throws ClassMetaDataException
     */
    public function handleOneToOneRelations(&$entity)
    {
        if (!empty($relations = $this->em->getClassMetaData()->getFullEntityRelations(RelationType::ONE_TO_ONE))) {
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
        if (!empty($relations = $this->em->getClassMetaData()->getFullEntityRelations(RelationType::ONE_TO_MANY))) {
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
        $this->em->setClassMetaData($entity);

        if (!empty($manyToManyRelations = $this->em->getClassMetaData()->getFullEntityRelations(RelationType::MANY_TO_MANY))) {
            // Only the owning side of the relation hydrates
            // a many-to-many association
            array_walk(
                $manyToManyRelations,
                function ($relation) use ($entity) {
                    if (isset($relation['inversedBy'])) {
                        $this->em->hydrateManyToManyRelation($relation, $entity);
                    }
                }
            );
        }
    }

    /**
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
            if ($this->em->getClassMetaData()->hasCascadePersist($data)) {
                $getMethod = 'get' . ucfirst($attribute);
                if ($type === RelationType::ONE_TO_ONE) {
                    try {
                        $this->em->persist($entity->$getMethod());
                    } catch (ClassMetaDataException $classMetaDataException) {
                        throw $classMetaDataException;
                    }
                    continue;
                }

                if ($type === RelationType::ONE_TO_MANY) {
                    foreach ($entity->$getMethod() as $targetEntity) {
                        try {
                            $this->em->persist($targetEntity);
                        } catch (ClassMetaDataException $classMetaDataException) {
                            throw $classMetaDataException;
                        }
                    }
                }
            }
        }
    }
}