<?php

namespace Lib\Form;

use Lib\Model\Orm\ClassMetaData;
use Lib\Model\Orm\EntityManager;
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
    private $em;

    /** @var Session $session */
    private $session;

    /** @var CollectionFormsManager $collectionFormsManager */
    private $collectionFormsManager;

    /** @var object $entity */
    private $entity;

    /** @var Field[] $fields */
    private $fields = [];

    /** @var string $name */
    private $name;

    /** @var Form $parent */
    private $parent;

    /** @var array $children */
    private $children = [];

    /** @var array $collections */
    private $collections = [];

    /** @var ClassMetaDataFactory $classMetaDataFactory */
    private $classMetaDataFactory;

    /** @var FormPrototypeBuilder $formPrototypeBuilder */
    private $formPrototypeBuilder;

    /** @var ClassMetaData $classMetaData */
    private $classMetaData;

    /** @var int $index */
    private $index;

    /** @var Request $request */
    private $request;

    /** @var bool $isFormPrototype */
    private $isFormPrototype;

    /** @var string $uniqueId */
    private $uniqueId;

    /** @var bool $isRequestHandled */
    private $isRequestHandled = false;

    /** @var array $options */
    private $options;

    /** @var bool $hasFiles */
    private $hasFiles;

    /**
     * Form constructor.
     * @param ClassMetaDataFactory $classMetaDataFactory
     * @param EntityManager $entityManager
     * @param $entity
     * @param Request $request
     * @param CollectionFormsManager $collectionFormsManager
     * @param FormPrototypeBuilder $formPrototypeBuilder
     * @param Session|null $session
     */
    public function __construct(
        ClassMetaDataFactory $classMetaDataFactory,
        EntityManager $entityManager,
        $entity,
        Request $request,
        CollectionFormsManager $collectionFormsManager,
        FormPrototypeBuilder $formPrototypeBuilder,
        Session $session = null
    )
    {
        $this->classMetaDataFactory   = &$classMetaDataFactory;
        $this->classMetaData          = $classMetaDataFactory->getClassMetaData($entity);
        $this->em                     = &$entityManager;
        $this->entity                 = &$entity;
        $this->request                = $request;
        $this->collectionFormsManager = $collectionFormsManager;
        $this->formPrototypeBuilder   = $formPrototypeBuilder;
        $this->session                = &$session;
        $this->name                   = get_class($this);

        $this->setUniqueId(md5(uniqid()));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getShortName()
    {
        return str_replace('Form\\', '', $this->name);
    }

    /**
     * @param Field $field
     *
     * @return $this
     */
    public function add(Field $field)
    {
        if (!$field->isCreated()) {

            $field->setParentForm($this);

            /** @var bool $isEntityHydrated if hydrated with the data in db */
            $isEntityHydrated = (null !== $this->entity->{
                $this->classMetaData->primaryKeyGetMethod()
            }());

            // Build the collection of forms
            if ($field->isCollection() && null !== $form = $field->getForm()) {

                // Storing the fact this embedded form is part of a collection
                // A form may have several collections of forms
                // See getParent()->getCollections()
                $this->collections[] = $form;

                // The target entity linked of the "embedded" form, not to "this" form
                // E.g 'images' => Entity\Image
                /** @var string $targetEntity */
                $targetEntity = $this->classMetaDataFactory->getTargetEntityByProperty(
                    $field->getName()
                );

                /** @var string|object $targetEntity */
                $targetEntity = $isEntityHydrated ? $this->entity : $targetEntity;

                $args = [$form, $targetEntity, $field, $this, $isEntityHydrated];

                // The user may have defined the number of forms in the collection
                if ($field->hasDefinedQuantity()) {
                    $args[] = $field->getDefinedQuantity();
                }

                $this->collectionFormsManager->createCollection(...$args);

                $this->children = $this->collectionFormsManager->getChildren();;
            }

            $this->updateCollectionName($field);

            $choices = [];

            if ($field->getType() === 'select') {
                $class = (new \ReflectionFunction($field->getChoices()))->getParameters()[0]->getClass();
                $modelName = $class->getShortName();
                $linkedEntity = preg_split("#Model#", $modelName)[0];
                $choices = $field->getChoices()($this->em->getEntityModel("Entity\\$linkedEntity"));
            }

            $field->create($choices);
            $this->fields[] = $field;

            // In case of collections, create child forms for the handle request
            // See CollectionFormManager (if is method post...)
            if ($this->request->isMethod(Request::METHOD_POST) &&
                !$this->isRequestHandled) {

                $this->buildChildren();
            }
        }

        return $this;
    }

    /**
     * @return string
     */
    public function createView()
    {
        $view = '';

        if (!$this->hasParent()) {
            $this->initView($view);
        }

        /** @var Field $field */
        foreach ($this->fields as $field) {
            $this->completeView($view, $field);
        }

        if (!$this->hasParent()) {
            $this->finishView($view);
        }

        return $view;
    }

    /**
     * @param Request $request
     * @param int $index: the form index in the handle request process
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

            /** @var string $parentForm */
            if ($this->hasParent()) {
                $parentForm = $this->getParent()->getName() . '_' . $this->getParent()->getIndex();
            }

            // Is the token still valid ?
            if ($givenToken === $receivedToken) {
                foreach ($request->post() as $key => $value) {
                    $setMethod = 'set' . ucfirst($key);
                    $getMethod = 'get' . ucfirst($key);
                    if (method_exists($this->entity, $setMethod)) {
                        // If the form is part of a collection
                        // The value is an array and the corresponding value of the field
                        // can be got using the form index
                        if ($this->hasParent()
                            && is_array($value)
                            && isset($value[$parentForm][$this->getName()][$this->getIndex()])) {
                            /** @var mixed $value */
                            $value = $value[$parentForm][$this->getName()][$this->getIndex()];
                        }

                        $this->entity->$setMethod(htmlspecialchars($value));

                        // Hydrate the form widget to send back to the view
                        foreach ($this->fields as $field) {
                            if (false !== strpos($field->getName(), $key)) {
                                $field->setValue($this->entity->$getMethod());
                                $field->update();
                            }
                        }
                    }
                }

                /** @var array $file */
                foreach ($request->files() as $file) {
                    if (isset($file['name'])) {
                        if (is_array($file['name'])
                            && $this->hasParent()
                            && isset($file['name'][$parentForm][$this->getName()][$this->getIndex()])) {
                                /** @var File $uploadedFile */
                                $uploadedFile = $this->createUploadedFile(
                                    $file,
                                    $parentForm,
                                    $this->getName(),
                                    $this->getIndex()
                                );
                        }

                        // Outermost form (parent of all forms)
                        if (!$this->hasParent() && !is_array($file['name'])) {
                            /** @var File $uploadedFile */
                            $uploadedFile = $this->createUploadedFile($file);
                        }

                        // Uploaded file may have not been set
                        if (!empty($uploadedFile)
                            && (method_exists($this->entity, $method = 'setFile') || method_exists($this->entity, $method = 'addFile'))) {
                            $this->entity->$method($uploadedFile);
                        }
                    }
                }

                /** @var FormInterface $form */
                foreach ($this->children as $form) {
                    // Calling the handle request of child form
                    // The children forms may themselves have children forms
                    // All the embedded forms linked entities will be hydrated
                    $form->handleRequest($request, $form->getIndex());
                };
            }

            // Do not remove key in children
            // Applied only for the outermost parent
            // This condition is not reached before treating
            // all the children forms
            if (!$this->hasParent()) {
                $request->getSession()->remove('_csrf_token');
                $this->isRequestHandled = true;
            }
        }
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
     *
     * @return $this
     */
    public function setIndex($index = 0)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * @return int|string
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

    /**
     * The embedded forms linked to this form
     *
     * See addField()
     *
     * @return array
     */
    public function getCollections()
    {
        return $this->collections;
    }

    /**
     * Called in collectionFormManager
     *
     * @param bool $prototype
     */
    public function setIsFormPrototype($prototype = false)
    {
        $this->isFormPrototype = $prototype;
    }

    /**
     * @param $file
     * @param null $parentForm
     * @param null $name
     * @param null $index
     * @return File
     */
    private function createUploadedFile(
        $file,
        $parentForm = null,
        $name = null,
        $index = null
    )
    {
        $subKey = !is_null($parentForm) && !is_null($name) && !is_null($index);

        $name     = $subKey ? $file['name'][$parentForm][$this->getName()][$this->getIndex()] : $file['name'];
        $type     = $subKey ? $file['type'][$parentForm][$this->getName()][$this->getIndex()] : $file['type'];
        $tmp_name = $subKey ? $file['tmp_name'][$parentForm][$this->getName()][$this->getIndex()] : $file['tmp_name'];
        $error    = $subKey ? $file['error'][$parentForm][$this->getName()][$this->getIndex()] : $file['error'];
        $size     = $subKey ? $file['size'][$parentForm][$this->getName()][$this->getIndex()] : $file['size'];

        return (new File($name, $type, $tmp_name, $error, $size));
    }

    /**
     * @return FormPrototypeBuilder
     */
    public function getFormPrototypeBuilder()
    {
        return $this->formPrototypeBuilder;
    }

    /**
     * @return Field[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param string $uniqueId
     */
    private function setUniqueId($uniqueId)
    {
        $this->uniqueId = $uniqueId;
    }

    /**
     * @return string
     */
    public function getUniqueId()
    {
        return $this->uniqueId;
    }

    /**
     * Must be persistent for recursive handle request call
     */
    private function addToken()
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
     * Function to build the children of the current form
     */
    private function buildChildren()
    {
        /**
         * @var int $key
         * @var FormInterface $childForm
         */
        foreach ($this->children as $key => $childForm) {
            if (!in_array($childForm->getUniqueId(), $this->collectionFormsManager->getTreatedChildren())) {
                $this->collectionFormsManager->addTreatedChild($childForm->getUniqueId());
                $childForm->createForm();
            }
        }
    }

    /**
     * @param Field $field
     */
    private function updateCollectionName(Field $field)
    {
        // Forms that are parts of a collection
        // $this = child
        if ($this->hasParent() &&
            in_array($this->getName(), $this->getParent()->getCollections())) {

            /** @var string $parentFormName */
            $parentFormName = $this->getParent()->getName() .
                ($this->getParent()->getIndex() === '_INDEX_'
                    ? '_INDEX_' : '_' . $this->getParent()->getIndex());

            $field->setNameForCollection(
                $parentFormName,
                $this->getName(),
                $this->getIndex()
            );

            $field->setIdForCollection($this->getIndex());
        }
    }

    /**
     * Only the parent form may have a token
     * @param string $view
     */
    private function initView(&$view)
    {
        $action     = $this->getOptions()['action'] ?? '/';
        $name = $id = $this->getOptions()['id'] ?? Tools::splitCamelCasedWords($this->getShortName());
        $method     = $this->getOptions()['method'] ?? 'POST';
        $view .= '<form method=' . $method. ' name='. $name . ' action=' . $action .' id=' . $id . ' enctype=multipart/form-data >';

        $this->addToken();
        $this->initPrototype();
    }

    /**
     * Function to init the forms collection prototype string
     */
    private function initPrototype()
    {
        if (!$this->hasParent()
            && !empty($this->getCollections())
            && empty($this->getFormPrototypeBuilder()->getPrototype())) {

            $this->getFormPrototypeBuilder()->initPrototype();
        }
    }

    /**
     * @param FormInterface $childForm
     */
    private function completePrototype($childForm)
    {
        $this
            ->getFormPrototypeBuilder()
            ->completePrototype($childForm);

        $childForm->createView();
    }

    /**
     * Function to return the built form collection prototype string
     *
     * @return string
     */
    private function getPrototype()
    {
        $prototype = '';

        if (isset($_GET['prototype'])
            && !empty($_GET['prototype'])) {

            $prototype = $_GET['prototype'];
            $prototype = "<div data-form=\"$prototype\"></div>";
        }

        return $prototype;
    }

    /**
     * Function to complete the view created with form prototype string
     * if required
     *
     * @param string $view
     * @return void
     */
    private function finishView(&$view)
    {
        if (!$this->session->has('prototype')) {
            $this->session->set('prototype', $this->getPrototype(), true);
        }

        $prototype = $this->session->get('prototype');
        $view .= $prototype;
        $view .= '</form>';
    }

    /**
     * @param string $view
     * @param Field $field
     */
    private function completeView(&$view, $field)
    {
        // Building the embedded form
        if ($field->isCollection()) {

            /** @var Form $childForm */
            foreach ($field->getForms() as $childForm) {
                // Do not add useless fields to the form to send back the right
                // number of fields to the view
                if (!$this->request->isMethod(Request::METHOD_POST)) {
                    $childForm->createForm();
                }

                // Recursive call
                if ($childForm->isFormPrototype &&
                    in_array(get_class($childForm), $childForm->getParent()->getCollections())) {
                    // Complete the prototype with each child form data
                    $this->completePrototype($childForm);
                } else {
                    $view .= $childForm->createView();
                }
            }
        } else {
            if (!$this->isFormPrototype) {
                if ($field->getType() === 'file') {
                    $this->setHasFiles(true);
                }

                $view .= $field->getWidget();
            }
        }
    }

    /**
     * @param array $options
     */
    public function setOptions($options = [])
    {
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return bool
     */
    public function getHasFiles()
    {
        return $this->hasFiles;
    }

    /**
     * @param $bool
     */
    public function setHasFiles($bool)
    {
        $this->hasFiles = $bool;
    }
}