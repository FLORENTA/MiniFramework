<?php

namespace Lib\Form;

/**
 * Class Field
 * @package Lib
 */
class Field extends FieldBuilder
{
    /** @var string $label */
    private $label = null;

    /** @var string $type */
    private $type = null;

    /** @var string $name */
    private $name = null;

    /** @var string $previous_name */
    private $previous_name;

    /** @var string $id */
    private $id;

    /** @var string $value */
    private $value = null;

    /** @var array $forms */
    private $forms = [];

    /** @var null $form */
    private $form = null;

    /** @var FormInterface $parentForm */
    private $parentForm;

    /** @var array $options */
    private $options = [];

    /** @var array $choices */
    private $choices = [];

    /** @var bool $created */
    private $created = false;

    /**
     * Field constructor.
     * Parameters contains label, input type ...
     * @param array $parameters
     */
    public function __construct($parameters = [])
    {
        foreach ($parameters as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }

        parent::__construct($this);
    }

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        $this->previous_name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
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
     * @param string|FormInterface $form
     */
    public function setForm($form)
    {
        $this->form = $form;
    }

    /**
     * @return string|FormInterface
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @param string|FormInterface $form
     */
    public function addForm($form)
    {
        $this->forms[] = $form;
    }

    /**
     * @return FormInterface[]
     */
    public function getForms()
    {
        return $this->forms;
    }

    /**
     * @param mixed $choices
     */
    public function setChoices($choices)
    {
        $this->choices = $choices;
    }

    /**
     * @return mixed
     */
    public function getChoices()
    {
        return $this->choices;
    }

    /**
     * @return string
     */
    public function getLinkedEntity()
    {
        return 'get' . ucfirst($this->name);
    }

    /**
     * Function to change the name of a field if the form
     * containing the field is part of a collection of forms
     *
     * @param string $parentFormName
     * @param string $formClass
     * @param int|string $index
     */
    public function setNameForCollection($parentFormName, $formClass, $index)
    {
        $this->name .= '[' . $parentFormName . '][' . $formClass . '][' . $index . ']';
    }

    /**
     * @param string $index
     */
    public function setIdForCollection($index)
    {
        $this->id .= '[' . $index . ']';
    }

    /**
     * @param bool $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     * @return bool
     */
    public function isCreated()
    {
        return $this->created;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isCollection()
    {
        return $this->type === 'collection';
    }

    /**
     * @return bool
     */
    public function hasDefinedQuantity()
    {
        return isset($this->getOptions()['quantity']);
    }

    /**
     * @return string|int
     */
    public function getDefinedQuantity()
    {
        if ($this->hasDefinedQuantity()) {
            return $this->getOptions()['quantity'];
        }

        return 1;
    }

    public function setParentForm($form)
    {
        $this->parentForm = $form;
    }

    /**
     * @return FormInterface
     */
    public function getParentForm()
    {
        return $this->parentForm;
    }

    /**
     * @return string
     */
    public function getPreviousName()
    {
        return $this->previous_name;
    }
}