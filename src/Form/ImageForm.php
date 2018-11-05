<?php

namespace Form;

use Classes\Form\Field;
use Classes\Form\Form;
use Entity\Image;

class ImageForm extends Form
{
    public function createForm()
    {
        // See form Builder
        $this->add(new Field([
            'label' => 'Choose a file',
            'name' => 'src',
            'type' => 'file',
            'options' => [
                'required' => 'true'
            ]
        ]));

//        $this->add(new Field([
//            'label' => 'user',
//            'name' => 'users',
//            'type' => Form::class,
//            'form' => UserForm::class
//        ]));
    }

    public function getLinkedEntity()
    {
        return Image::class;
    }
}