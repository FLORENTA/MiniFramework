<?php

namespace Lib\Form;

use Lib\Http\Request;

/**
 * Interface FormInterface
 * @package Lib\Form
 */
interface FormInterface
{
    /** @param Field $field */
    public function add(Field $field);

    /**
     * @param Request $request
     * @param int $index
     * @return mixed
     */
    public function handleRequest(Request $request, $index);

    /** @return string */
    public function createView();

    /** @param FormInterface $form */
    public function setParent(FormInterface $form);

    /** @return object */
    public function getEntity();

    // The entity path linked to the form, E.g : Dummy::class
    /** @return string */
    public function getLinkedEntity();

    public function createForm();
}