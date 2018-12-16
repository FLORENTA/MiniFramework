<?php

namespace Lib\Model\Orm;

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
     */
    public function handleRelations(&$entity)
    {
        $this
            ->handleOneToOneRelations($entity)
            ->handleOneToManyRelations($entity)
            ->handleManyToManyRelations($entity);
    }

    /**
     * @param object $entity
     *
     * @return $this
     */
    public function handleOneToOneRelations(&$entity)
    {
        if (!empty($relations = $this->em->getClassMetaData()->getFullEntityRelations(RelationType::ONE_TO_ONE))) {
            $this->cascadePersist(
                $relations,
                $entity,
                RelationType::ONE_TO_ONE
            );
        }

        return $this;
    }

    /**
     * @param object $entity
     *
     * @return $this
     */
    public function handleOneToManyRelations(&$entity)
    {
        if (!empty($relations = $this->em->getClassMetaData()->getFullEntityRelations(RelationType::ONE_TO_MANY))) {
            $this->cascadePersist(
                $relations,
                $entity,
                RelationType::ONE_TO_MANY
            );
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
     */
    public function cascadePersist($relations, &$entity, $type)
    {
        foreach ($relations as $attribute => $data) {
            if ($this->em->getClassMetaData()->hasCascadePersist($data)) {
                $getMethod = 'get' . ucfirst($attribute);
                if ($type === RelationType::ONE_TO_ONE) {
                    $this->em->persist($entity->$getMethod());
                    continue;
                }

                if ($type === RelationType::ONE_TO_MANY) {
                    foreach ($entity->$getMethod() as $targetEntity) {
                        $this->em->persist($targetEntity);
                    }
                }
            }
        }
    }
}