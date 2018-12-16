<?php

namespace Lib\Form;

use Lib\Http\Request;
use Lib\Model\Orm\ClassMetaData;
use Lib\Model\Orm\ClassMetaDataFactory;
use Lib\Model\Orm\EntityManager;
use Lib\Model\Relation\RelationType;
use Lib\Utils\Tools;

/**
 * Class CollectionFormsManager
 * @package Lib\Form
 */
class CollectionFormsManager
{
    /** @var ClassMetaDataFactory $classMetaDataFactory */
    private $classMetaDataFactory;

    /** @var EntityManager $em */
    private $em;

    /** @var Request $request */
    private $request;

    /** @var Form[] $children */
    private $children;

    /** @var string $parentFieldAddMethod */
    private $parentFieldAddMethod;

    /** @var array $tree */
    private $tree = [];

    /** @var array $treatedChildren */
    private $treatedChildren = [];

    /**
     * CollectionFormsManager constructor.
     * @param ClassMetaDataFactory $classMetaDataFactory
     * @param EntityManager $entityManager
     * @param Request $request
     */
    public function __construct(
        ClassMetaDataFactory $classMetaDataFactory,
        EntityManager $entityManager,
        Request $request
    )
    {
        $this->classMetaDataFactory = $classMetaDataFactory;
        $this->em                   = $entityManager;
        $this->request              = $request;

        \call_user_func([$this, 'buildTreeOfCollectionForms']);
    }

    /**
     * Function to build a tree for the collection of forms
     */
    private function buildTreeOfCollectionForms()
    {
        foreach ($this->request->post() as $name => $value) {
            if (is_array($value)) {
                array_walk($value, function($v, $key) {
                    $this->add($key, $v);
                });
            }
        }

        foreach ($this->request->files() as $key => $file) {
            if (is_array($file['name'])) {
                array_walk($file['name'], function($v, $k) {
                    $this->add($k, $v);
                });
            }
        }
    }

    /**
     * @param string $parent
     * @param array $val
     */
    private function add($parent, $val)
    {
        foreach ($val as $k => $subVal) {
            $this->tree[$parent][$k] = count($subVal);
        }
    }

    /**
     * @param string $formClass
     * @param string $targetEntity
     * @param Field $parentField
     * @param Form $parentForm
     * @param bool $isEntityHydrated
     * @param int $quantity
     */
    public function createCollection(
        $formClass,
        $targetEntity,
        $parentField,
        $parentForm,
        $isEntityHydrated = false,
        $quantity = 0
    )
    {
        // Reinitializing children for next collections to create
        $this->children = [];

        // To add the entity to the parent entity
        // See below in getForm method
        $this->parentFieldAddMethod =
            'add' . ucfirst(Tools::TransformEndOfWord($parentField->getName()));

        $args = [
            $formClass,
            $targetEntity,
            $parentField,
            $parentForm,
            $isEntityHydrated
        ];

        // If the user loads an entity from the database
        // Let's hydrate the form with the data of the entity their relations
        if ($isEntityHydrated) {
            $getMethod = 'get' . ucfirst($parentField->getName());
            if (method_exists($targetEntity, $getMethod)) {
                /** @var array $entities */
                $entities = $targetEntity->$getMethod();
                foreach ($entities as $key => &$entity) {
                    $args[1] = $entity;
                    $args[5] = $key;
                    $this->children[] = $this->getForm(...$args);
                }
            }
        // Has the form been submitted with an "unknown number of added fields ([_INDEX_] replaced)" ?
        } elseif ($this->request->isMethod(Request::METHOD_POST)) {
            // Determining the number of forms received by collection
            foreach ($this->tree as $parent => $child) {
                if ($parentForm->getName() . '_' . $parentForm->getIndex() === $parent) {
                    /** @var int $count */
                    $count = $child[$formClass];
                    $this->createForms($count, $args);
                }
            }
        } else {
            // A quantity may have been defined by default in the options
            // If not, let's generate a prototype, _INDEX_ will be replace by 0, 1, 2 ...
            if ($quantity !== 0) {
                $this->createForms($quantity, $args);
            } else {
                $args[5] = '_INDEX_';
                $this->children[] = $this->getForm(...$args);
            }
        }
    }

    /**
     * @param int $quantity
     * @param array $args
     */
    private function createForms($quantity, $args)
    {
        for ($i = 0; $i < $quantity; $i++) {
            $args[5] = $i;
            $this->children[] = $this->getForm(...$args);
        }
    }

    /**
     * @return Form[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param string $formClass
     * @param string $targetEntity
     * @param Field $parentField
     * @param Form $parentForm
     * @param bool $isEntityHydrated
     * @param int|string $index
     * @return Form
     */
    private function getForm(
        $formClass,
        $targetEntity,
        $parentField,
        $parentForm,
        $isEntityHydrated,
        $index
    )
    {
        /** @var object $linkedEntity */
        $linkedEntity = !$isEntityHydrated ? new $targetEntity : $targetEntity;

        // Hydrate the parent form linked entity
        // [ fill its collection fields with empty entity|ies that will be filled
        // by the handle request method executed after the form creation ]
        // May not be callable if not bidirectional
        if (method_exists($parentForm->getEntity(), $this->parentFieldAddMethod)) {
            $parentForm->getEntity()->{$this->parentFieldAddMethod}($linkedEntity);
        }

        /** @var ClassMetaData $entityMetaData */
        $entityMetaData = $this
            ->classMetaDataFactory
            ->getClassMetaData($linkedEntity);

        // Hydrate with the linked entity
        if ($entityMetaData->hasRelations(RelationType::MANY_TO_ONE)) {
            /** @var array $manyToOneRelations */
            $manyToOneRelations = $entityMetaData->getRelations(RelationType::MANY_TO_ONE);
            /**
             * @var string $attribute
             * @var array $relation
             */
            foreach ($manyToOneRelations as $attribute => $relation) {
                if ($relation['target'] === get_class($parentForm->getEntity())) {
                    $setMethod = 'set' . ucfirst($attribute);
                    // May not be callable if not bidirectional
                    // E.G one dummy has many images
                    // each image set "this" dummy
                    if (method_exists($linkedEntity, $setMethod)) {
                        $linkedEntity->$setMethod($parentForm->getEntity());
                    }
                }
            }
        }

        // Hydrate with the linked entity
        if ($entityMetaData->hasRelations(RelationType::MANY_TO_MANY)) {
            /** @var array $manyToOneRelations */
            $manyToManyRelations = $entityMetaData->getRelations(RelationType::MANY_TO_MANY);
            /**
             * @var string $attribute
             * @var array $relation
             */
            foreach ($manyToManyRelations as $attribute => $relation) {
                if ($relation['target'] === get_class($parentForm->getEntity())) {
                    $addMethod = 'add' . ucfirst(Tools::TransformEndOfWord($attribute));
                    // May not be callable if not bidirectional
                    // E.G one dummy has many images
                    // each image set "this" dummy
                    if (method_exists($linkedEntity, $addMethod)) {
                        $linkedEntity->$addMethod($parentForm->getEntity());
                    }
                }
            }
        }

        /** @var Form $form */
        $form = new $formClass(
            $this->classMetaDataFactory,
            $this->em,
            $linkedEntity,
            $this->request,
            $this,
            $parentForm->getFormPrototypeBuilder()
        );

        $form->setIsFormPrototype($index === '_INDEX_');

        // Set index of the child form
        // It will be used in the handle request method
        // to get the data corresponding to the form index
        // eg : images[0], images[1]...
        $form->setIndex($index);

        // Set the parent of this form
        $form->setParent($parentForm);

        // Register the child form in the collection
        $parentField->addForm($form);

        return $form;
    }

    /**
     * @param FormInterface $child
     */
    public function addTreatedChild($child)
    {
        $this->treatedChildren[] = $child;
    }

    /**
     * @return array
     */
    public function getTreatedChildren()
    {
        return $this->treatedChildren;
    }
}