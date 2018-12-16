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
    public function handleRequest(Request $request, $index = 0);

    /** @return string */
    public function createView();

    /** @param FormInterface $form */
    public function setParent(FormInterface $form);

    /** @return string */
    public function getName();

    /** @return object */
    public function getEntity();

    // The entity path linked to the form, E.g : Dummy::class
    /** @return string */
    public function getLinkedEntity();

    // See src\Form
    public function createForm();

    /** @param int $index */
    public function setIndex($index = 0);

    /** @return int|string */
    public function getIndex();

    /** @return FormInterface */
    public function getParent();

    /** @return Field[] */
    public function getFields();

    /** @return string */
    public function getShortName();

    /** @return bool */
    public function hasParent();

    /** @param array $options */
    public function setOptions($options = []);

    /** @return array */
    public function getOptions();

    /** @param bool $bool */
    public function setHasFiles($bool);

    /** @return bool */
    public function getHasFiles();
}