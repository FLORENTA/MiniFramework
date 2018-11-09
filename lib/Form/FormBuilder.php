<?php

namespace Lib\Form;

use Lib\Http\Session;
use Lib\Model\Orm\ClassMetaDataFactory;

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

    /** @var Form $form */
    private $form;

    /**
     * FormBuilder constructor.
     * @param Session $session
     */
    public function __construct(Session $session, ClassMetaDataFactory $classMetaDataFactory)
    {
        $this->session = $session;
        $this->classMetaDataFactory = $classMetaDataFactory;
    }

    /**
     * @param string $form
     * @param string $entity
     */
    public function createForm($form, $entity)
    {
        // Instantiating the given form, with the given entity for hydration
        $this->form = new $form(
            $this->session,
            $this->classMetaDataFactory,
            $entity
        );

        // Call the createForm method of the given form
        // For instance, DummyForm
        $this->form->createForm();
    }

    /**
     * @return Form
     */
    public function getForm()
    {
        return $this->form;
    }
}