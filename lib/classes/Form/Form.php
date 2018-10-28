<?php

namespace Classes\Form;

use Classes\Http\Request;
use Classes\Http\Session;

/**
 * Class Form
 * @package Classes
 */
class Form
{
    /** @var Session $session */
    protected $session;

    /** @var object $entity */
    protected $entity;

    /** @var array $fields */
    protected $fields = [];

    /**
     * Form constructor.
     * @param Session $session
     * @param null $entity
     */
    public function __construct(Session $session, $entity = null)
    {
        $this->session = $session;
        $this->entity = $entity;
    }

    /**
     * @param Field $field
     * @return $this
     */
    public function add(Field $field)
    {
        $name = 'get' . ucfirst($field->getName());

        if ($field->getName() !== '_csrf_token') {
            if (!is_null($this->entity)) {
                $value = $this->entity->$name(); // Will serve when getting data from the db
                $field->setValue($value);
            }
        }

        $this->fields[] = $field;

        return $this;
    }

    public function addToken()
    {
        $token = md5(uniqid());
        $this->session->set('_csrf_token', $token);
        $this->add(new Field([
            'label' => false,
            'type' => 'hidden',
            'name' => '_csrf_token',
            'options' => [
                'value' => $token
            ]
        ]));
    }

    /**
     * @return string
     */
    public function createView()
    {
        // Cannot be set before creating view
        // Otherwise, the token may change and handleRequest would always be false
        $this->addToken();

        $form = '';

        foreach ($this->fields as $field) {
            $form .= $field->getWidget();
        }

        return $form;
    }

    /**
     * @param Request $request
     */
    public function handleRequest(Request $request)
    {
        if ($request->get('_csrf_token') === $this->session->get('_csrf_token')) {
            foreach ($request->post() as $key => $value) {
                if (method_exists($this->entity, $method = 'set' . ucfirst($key))) {
                    $this->entity->$method(htmlspecialchars($value));
                }
            }
        } else {
            throw new \RuntimeException("The form validity has expired.");
        }
    }
}