<?php

namespace Classes\Form;

use Classes\Http\Request;
use Classes\Http\Session;
use Classes\Model\Orm\ClassMetaDataFactory;

/**
 * Class Form
 * @package Classes
 */
class Form implements FormInterface
{
    /** @var Session $session */
    protected $session;

    /** @var object $entity */
    protected $entity;

    /** @var array $fields */
    protected $fields;

    /** @var string $name */
    protected $name;

    /** @var Form $parent */
    protected $parent;

    /** @var array $children */
    protected $children = [];

    /** @var ClassMetaDataFactory $classMetaDataFactory */
    protected $classMetaDataFactory;

    /**
     * Form constructor.
     * @param Session $session
     * @param ClassMetaDataFactory $classMetaDataFactory
     * @param object $entity
     */
    public function __construct(
        Session $session,
        ClassMetaDataFactory $classMetaDataFactory,
        $entity
    )
    {
        $this->session              = $session;
        $this->classMetaDataFactory = $classMetaDataFactory;
        $this->entity               = $entity;
    }

    /**
     * // TODO check on name given
     * @param Field $field
     */
    public function add(Field $field)
    {
        if ($field->getType() === Form::class) {
            /** @var string $form */
            $form = $field->getForm();
            /** @var  $classMetaData */
            $attribute = $field->getName();
            /** @var string $targetClass */
            $targetClass = $this->classMetaDataFactory->getTargetEntityByProperty($attribute);
            $getMethod = 'get' . ucfirst($attribute);
            /** @var object $givenEntity, the entity attached to the object */
            $givenEntity = $this->entity->$getMethod();

            /* Does the given entity correspond to the child form
             * linked entity returned value
             */
            if (!$givenEntity instanceof $targetClass) {
                throw new \Exception(
                    sprintf('The entity given for field %s must
                    be an instance of %s', $attribute, $targetClass)
                );
            }

            /** @var Form $form */
            $form = new $form(
                $this->session,
                $this->classMetaDataFactory,
                $givenEntity
            );

            $form->setParent($this);
            $field->setForm($form);
            $this->children[] = $form;
        }

        $this->fields[] = $field;
    }

    /**
     * @throws \Exception
     */
    public function addCsrfToken()
    {
        $token = md5(uniqid());
        // Must be persistent for recursive calls to handle request
        $this->session->set('_csrf_token', $token, true);
        $this->add(new Field([
            'type' => 'hidden',
            'name' => '_csrf_token',
            'value' => $token
        ]));
    }

    public function handleRequest(Request $request)
    {
        if ($request->getSession()->get('_csrf_token') === $request->get('_csrf_token')) {
            foreach ($request->post() as $key => $value) {
                if (method_exists($this->entity, $method = 'set' . ucfirst($key))) {
                    $this->entity->$method(htmlspecialchars($value));
                }
            }

            foreach ($this->children as $form) {
                // Calling the handle request of each form
                // As the children forms may themselves have children forms
                // All the embedded forms linked entities will be hydrated
                $form->handleRequest($request);
            }
        }

        $request->getSession()->remove('_csrf_token');
    }

    /**
     * @return string
     */
    public function createView()
    {
        $view = '';

        // The children forms must not have a csrf_token
        if (!$this->hasParent()) {
            $this->addCsrfToken();
        }

        /** @var Field $field */
        foreach ($this->fields as $field) {
            // Building the embedded form
            if ($field->getType() === Form::class) {
                /** @var Form $form */
                $form = $field->getForm();
                // Creating the children forms registered and their children...
                $form->createForm();
                $view .= $form->createView();
            } else {
                $view .= $field->getWidget();
            }
        }

        return $view;
    }

    /**
     * @param FormInterface $parent
     */
    public function setParent(FormInterface $parent)
    {
        $this->parent = $parent;
    }

    /**
     * @return bool
     */
    public function hasParent()
    {
        return $this->parent instanceof FormInterface;
    }

    /**
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }
}