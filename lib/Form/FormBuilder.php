<?php

namespace Lib\Form;

use Lib\Http\Request;
use Lib\Http\Session;
use Lib\Model\Orm\ClassMetaDataFactory;
use Lib\Model\Orm\EntityManager;

/**
 * Class FormBuilder
 * @package Lib
 */
class FormBuilder
{
    /** @var Session $session */
    private $session;

    /** @var ClassMetaDataFactory $classMetaDataFactory */
    private $classMetaDataFactory;

    /** @var EntityManager $em */
    private $em;

    /** @var CollectionFormsManager $collectionFormsManager */
    private $collectionFormsManager;

    /** @var Form $form */
    private $form;

    /**
     * FormBuilder constructor.
     * @param ClassMetaDataFactory $classMetaDataFactory
     * @param Session $session
     * @param EntityManager $entityManager
     * @param CollectionFormsManager $collectionFormsManager
     */
    public function __construct(
        ClassMetaDataFactory $classMetaDataFactory,
        Session $session,
        EntityManager $entityManager,
        CollectionFormsManager $collectionFormsManager
    )
    {
        $this->classMetaDataFactory   = $classMetaDataFactory;
        $this->session                = $session;
        $this->em                     = $entityManager;
        $this->collectionFormsManager = $collectionFormsManager;
    }

    /**
     * @param string $form
     * @param string $entity
     * @param Request $request
     */
    public function createForm($form, $entity, Request $request)
    {
        if (!\is_object($entity)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid entity argument given for %s', $form)
            );
        }

        // Instantiating the given form, with the given entity for hydration
        /** @var Form $f */
        $this->form = $f = new $form(
            $this->classMetaDataFactory,
            $this->em,
            $entity,
            $request,
            $this->collectionFormsManager,
            $this->session
        );

        // The entity that is linked to the form
        /** @var string $linkedEntity */
        $linkedEntity = $f->getLinkedEntity();

        if (get_class($entity) !== $linkedEntity && !is_null($linkedEntity)) {
            throw new \InvalidArgumentException(
                sprintf('The entity given to %s must be an instance of %s',
                    $form, $linkedEntity
                )
            );
        }

        // Call the createForm method of the given form
        // For instance, DummyForm
        $f->setIndex(0);
        $f->createForm();
    }

    /**
     * @return Form
     */
    public function getForm()
    {
        return $this->form;
    }
}