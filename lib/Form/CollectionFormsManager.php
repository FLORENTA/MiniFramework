<?php

namespace Lib\Form;

use Lib\Http\Request;
use Lib\Model\Orm\ClassMetaDataFactory;
use Lib\Model\Orm\EntityManager;
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
    }

    /**
     * @param string $formClass
     * @param string $targetEntity
     * @param Form $parent
     * @param Field $parentField
     * @param int $quantity
     * @throws \ReflectionException
     */
    public function createCollection(
        $formClass,
        $targetEntity,
        &$parent,
        &$parentField,
        $quantity = 0
    )
    {
        $this->parentFieldAddMethod = 'add' . ucfirst(Tools::TransformEndOfWord($parentField->getName()));

        $args = [$formClass, $targetEntity, $parentField, $parent];

        $reflectionMethod = new \ReflectionMethod($this, 'getForm');

        // Has the form been submitted with an "unknown number of added fields ([_INDEX_] replaced)" ?
        if ($this->request->isMethod(Request::METHOD_POST)) {
            // Determining the number of forms received by collection
            foreach ($this->request->post() as $name => $value) {
                if (is_array($value)) {
                    $number = count($value);
                }
            }
        } else {
            // A quantity may have been defined by default in the options
            if ($quantity !== 0) {
                for ($i = 0; $i < $quantity; ++$i) {
                    $args = array_merge($args, $i);
                    $this->children[] = $reflectionMethod->invokeArgs($this, $args);
                }
            } else {
                $args = array_merge($args, ['_INDEX_']);
                $this->children[] = $reflectionMethod->invokeArgs($this, $args);
            }
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
     * @param $index
     * @return Form
     */
    public function getForm($formClass, $targetEntity, $parentField, $parentForm, $index)
    {
        /** @var object $linkedEntity */
        $linkedEntity = new $targetEntity;

        // Hydrate the parent form linked entity
        // [ fill its collection fields with empty entity|ies that will be filled
        //   by the handle request method executed after the form creation ]
        call_user_func([$parentForm->getEntity(), $this->parentFieldAddMethod], $linkedEntity);

        /** @var Form $form */
        $form = new $formClass(
            $this->classMetaDataFactory,
            $this->em,
            $linkedEntity,
            $this->request,
            $this
        );

        // Set index of the children form
        // It will be used in the handle request method
        // to get the data corresponding to the form
        // eg : images[0], images[1]...
        $form->setIndex($index);
        // Save the parent of the child form
        $form->setParent($parentForm);
        // Register the child form in the collection
        $parentField->addForm($form);

        return $form;
    }
}