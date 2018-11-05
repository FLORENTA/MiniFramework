<?php

namespace Form;

use Classes\Form\Field;
use Classes\Form\Form;
use Entity\User;

class UserForm extends Form
{
    public function createForm()
    {
        $this->add(new Field([
            'label' => 'user',
            'name' => 'username',
            'type' => 'text'
        ]));
    }

    public function getLinkedEntity()
    {
        return User::class;
    }
}