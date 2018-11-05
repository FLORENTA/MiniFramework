<?php

namespace Classes\Form;

use Classes\Http\Request;

/**
 * Interface FormInterface
 * @package Classes\Form
 */
interface FormInterface
{
    /**
     * @param Field $field
     */
    public function add(Field $field);

    /**
     * @param Request $request
     */
    public function handleRequest(Request $request);

    /**
     * @return string
     */
    public function createView();

    /**
     * @param FormInterface $form
     */
    public function setParent(FormInterface $form);

    /**
     * @return object
     */
    public function getEntity();
}