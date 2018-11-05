<?php

namespace Classes\Form;

use Classes\Http\Session;
use Classes\Model\Orm\ClassMetaDataFactory;

/**
 * Class FormBuilder
 * @package Classes
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
        $this->form = new $form(
            $this->session,
            $this->classMetaDataFactory,
            $entity
        );

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