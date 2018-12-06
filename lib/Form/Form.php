<?php

namespace Lib\Form;

use Entity\User;
use Lib\Model\Orm\ClassMetaData;
use Lib\Model\Orm\EntityManager;
use Lib\Throwable\Form\FormException;
use Lib\Http\Request;
use Lib\Http\Session;
use Lib\Model\Orm\ClassMetaDataFactory;
use Lib\Utils\Tools;

/**
 * Class Form
 * @package Lib
 */
abstract class Form implements FormInterface
{
    /** @var EntityManager $em */
    protected $em;

    /** @var Session $session */
    protected $session;

    /** @var CollectionFormsManager $collectionFormsManager */
    private $collectionFormsManager;

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

    /** @var array $collections */
    protected $collections = [];

    /** @var ClassMetaDataFactory $classMetaDataFactory */
    protected $classMetaDataFactory;

    /** @var ClassMetaData $classMetaData */
    private $classMetaData;

    /** @var int $index */
    private $index;

    /** @var Request $request */
    private $request;

    /**
     * Form constructor.
     * @param ClassMetaDataFactory $classMetaDataFactory
     * @param EntityManager $entityManager
     * @param $entity
     * @param Request $request
     * @param CollectionFormsManager $collectionFormsManager
     * @param Session|null $session
     */
    public function __construct(
        ClassMetaDataFactory $classMetaDataFactory,
        EntityManager $entityManager,
        $entity,
        Request $request,
        CollectionFormsManager $collectionFormsManager,
        Session $session = null
    )
    {
        $this->classMetaDataFactory   = &$classMetaDataFactory;
        $this->classMetaData          = $classMetaDataFactory::getClassMetaData($entity);
        $this->em                     = &$entityManager;
        $this->entity                 = &$entity;
        $this->request                = $request;
        $this->collectionFormsManager = $collectionFormsManager;
        $this->session                = &$session;
    }

    // See child extending this abstract class
    abstract public function createForm();

    // The entity path linked to the form, E.g : Dummy::class
    abstract public function getLinkedEntity();

    /**
     * // TODO check on name given
     * @param Field $field
     * @throws FormException
     * @throws \ReflectionException
     */
    public function add(Field $field)
    {
        // Build the collection of forms
        if ($field->getType() === 'collection') {

            /** @var string $form */
            $form = $field->getForm();

            // Storing the fact this embedded form is part of a collection
            // A form may have several collections of forms
            $this->collections[] = $form;

            // The target entity linked of the embedded form
            /** @var string $targetEntity */
            $targetEntity = $this->classMetaDataFactory->getTargetEntityByProperty(
                $field->getName()
            );

            // Treating collection of forms
            if (isset($field->getOptions()['quantity'])) {

                /** @var int $q */
                $q = $field->getOptions()['quantity'];

                $this->collectionFormsManager->createCollection(
                    $form,
                    $targetEntity,
                    $this,
                    $field,
                    $q
                );

                $this->children = $this->collectionFormsManager->getChildren();
            } else {
                $this->collectionFormsManager->createCollection(
                    $form,
                    $targetEntity,
                    $this,
                    $field
                );
                $this->children = $this->collectionFormsManager->getChildren();
            }
        }

        // Forms that are parts of a collection
        if ($this->hasParent()) {

            // E.g : Image::Form
            $form = get_class($this);

            if (in_array($form, $this->getParent()->getCollections())) {
                $field->setNameForCollection($this->getIndex());
                $field->setIdForCollection($this->getIndex());
            }
        }

        if (!$field->isCreated()) {
            $field->create();
        }

        $this->fields[] = $field;
    }

    /**
     * Must be persistent for recursive handle request call
     *
     * @throws FormException
     */
    public function addToken()
    {
        $token = md5(uniqid());
        $this->session->set('_csrf_token', $token, true);
        $this->add(new Field([
            'type' => 'hidden',
            'name' => '_csrf_token',
            'value' => $token
        ]));
    }

    /**
     * @param Request $request
     * @param int $index: the form index in the handle request process
     *
     *
     * If outermost parent : the index is 0
     * Intermediate forms level that are in a parent collection :
     *     index starts at 0 and ends at x
     */
    public function handleRequest(Request $request, $index = 0)
    {
        if ($request->isMethod(Request::METHOD_POST)) {

            /** @var string $receivedToken */
            $receivedToken = $request->get('_csrf_token');

            /** @var string $givenToken */
            $givenToken = $request->getSession()->get('_csrf_token');

            // Is the token still valid ?
            if ($givenToken === $receivedToken) {
                foreach ($request->post() as $key => $value) {
                    $method = 'set' . ucfirst($key);
                    if (method_exists($this->entity, $method)) {
                        // If the form is part of a collection
                        // The value is an array and the corresponding value of the field
                        // can be got using the form index
                        $value = is_array($value) ? $value[$index] : $value;
                        $this->entity->$method(htmlspecialchars($value));
                    }
                }

                /** @var array $files
                 */
                $files = $request->files();

                if (!empty($files)) {
                    /** @var File $uploadedFile */
                    $uploadedFile = (new File())
                        ->setName($files['file']['name'][$index])
                        ->setType($files['file']['type'][$index])
                        ->setTmpName($files['file']['tmp_name'][$index])
                        ->setError($files['file']['error'][$index])
                        ->setSize($files['file']['size'][$index]);

                    if (method_exists($this->entity, $method = 'setFile')) {
                        $this->entity->$method($uploadedFile);
                    }
                }

                /** @var Form $form */
                foreach ($this->children as $form) {
                    // Calling the handle request of each form
                    // As the children forms may themselves have children forms
                    // All the embedded forms linked entities will be hydrated
                    $form->handleRequest($request, $form->getIndex());
                }
            }
        }
        // Do not remove key in children !!
        // Applied only for the outermost parent
        // This condition is not reached before treating all the children forms
        // See handle request recursive call
        if (!$this->hasParent()) {
            $request->getSession()->remove('_csrf_token');
        }
    }

    /**
     * @return string
     * @throws FormException
     */
    public function createView()
    {
        $view = '';

        // Only the parent form may have a token
        if (!$this->hasParent()) {
            $this->addToken();
        }

        /** @var Field $field */
        foreach ($this->fields as $field) {

            if ($field->getType() === 'select') {
                var_dump($field->getChoices()($this->em->getEntityModel(User::class)));
            }

            // Building the embedded form
            if ($field->getType() === 'collection') {
                /** @var FormInterface $childForm */
                foreach ($field->getForms() as $childForm) {
                    $childForm->createForm();
                    $view .= $childForm->createView();
                }
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
     * @return Form
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return bool
     */
    public function hasParent()
    {
        return $this->parent instanceof FormInterface;
    }

    /**
     * The form index in the collection or 0 if not part of a collection
     *
     * @param int $index
     */
    public function setIndex($index)
    {
        $this->index = $index;
    }

    /**
     * @return int
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }

    public function getCollections()
    {
        return $this->collections;
    }
}