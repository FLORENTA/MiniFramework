<?php

namespace Lib\Form;

/**
 * Class FormPrototypeBuilder
 * @package Lib\Form
 */
class FormPrototypeBuilder
{
    public function initPrototype()
    {
        $_GET['prototype'] = '';
    }

    /**
     * Function to complete a prototype
     *
     * If a form has children forms without precision
     * about the quantity to render, the children forms
     * collection will be a string with an "_INDEX_"
     * mask to increment in view thanks to a bit of javascript
     *
     * If the children forms have themselves children forms
     * the prototype will be completed with the data of
     * those children [ fields except collection ]
     *
     * @param FormInterface $childForm
     */
    public function completePrototype(FormInterface $childForm)
    {
        $fields = $childForm->getFields();

        $prototype = $this->getPrototype();

        $prototype .= '<div data-form-name=' . $childForm->getShortName() .'>';

        /** @var Field $field */
        foreach ($fields as $field) {
            if (!$field->isCollection()) {
                $prototype .= $field->getWidget();
            }
        }

        $prototype .= '</div>';

        $this->setPrototype($prototype);
    }

    /**
     * @return string
     */
    public function getPrototype()
    {
        return $_GET['prototype'] ?? '';
    }

    /**
     * @param string $prototype
     */
    public function setPrototype($prototype)
    {
        $_GET['prototype'] = $prototype;
    }
}