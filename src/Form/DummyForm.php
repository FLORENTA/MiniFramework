<?php

namespace Form;

use Lib\Form\Field;
use Lib\Form\Form;
use Entity\Dummy;

/**
 * Class DummyForm
 * @package Form
 */
class DummyForm extends Form
{
    public function createForm()
    {
        // See form Builder
        $this->add(new Field([
            'label' => 'Choose a title',
            'name' => 'title',
            'type' => 'text',
            'options' => [
                'required' => 'true'
            ]
        ]));

        $this->add(new Field([
            'name' => 'image',
            'type' => Form::class,
            'form' => ImageForm::class
        ]));
    }

    public function getLinkedEntity()
    {
        return Dummy::class;
    }
}