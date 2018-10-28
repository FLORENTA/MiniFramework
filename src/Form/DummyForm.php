<?php

namespace Form;

use Classes\Form\Field;
use Classes\Form\FormBuilder;

/**
 * Class DummyForm
 * @package Form
 */
class DummyForm extends FormBuilder
{
    public function createForm()
    {
        // See form Builder
        $this->form->add(new Field([
                'label' => 'Choose a number',
                'name' => 'number',
                'type' => 'number',
                'options' => [
                    'min' => '2',
                    'required' => 'true'
                ]
            ]));
    }
}