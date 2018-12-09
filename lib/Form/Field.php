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

    /** @var string $id */
    private $id;

    /** @var string $value */
    private $value = null;

    /** @var array $forms */
    private $forms = [];

    /** @var null $form */
    private $form = null;

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
     */
    public function setLabel($label)
    {
        $this->label = $label;
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
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
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

    public function setNameForCollection($index)
    {
        $this->name .= '['. $index .']';
    }

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
}