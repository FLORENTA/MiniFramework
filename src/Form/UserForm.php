<?php

namespace Form;

use Lib\Form\Field;
use Lib\Form\Form;
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