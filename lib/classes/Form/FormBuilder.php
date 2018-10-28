<?php

namespace Classes\Form;

use Classes\Http\Session;

/**
 * Class FormBuilder
 * @package Classes
 */
class FormBuilder
{
    /**
     * @var Form $form
     */
    protected $form;

    /**
     * FormBuilder constructor.
     * @param Session $session
     * @param null $entity
     */
    public function __construct(Session $session, $entity = null)
    {
        $this->form = new Form($session, $entity);
    }

    /**
     * @return Form
     */
    public function getForm()
    {
        return $this->form;
    }
}