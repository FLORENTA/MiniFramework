<?php

namespace Lib\Form;

/**
 * Class Field
 * @package Lib
 */
class Field
{
    /** @var array $parameters */
    protected $parameters;

    /** @var string $label */
    protected $label;

    /** @var string $type */
    protected $type;

    /** @var string $name */
    protected $name;

    /** @var string $value */
    protected $value;

    /** @var string $form */
    protected $form;

    /** @var array $options */
    protected $options;

    /**
     * Field constructor.
     * Parameters contains label, input type ...
     * @param array $parameters
     */
    public function __construct($parameters = [])
    {
        foreach ($parameters as $key => $value) {
            $method = 'set' . ucfirst($key);
            $this->$method($value);
        }
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
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
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
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
     * @return string
     */
    public function getWidget()
    {
        $field = '';

        if (false !== $this->getLabel()) {
            $field .= '<label for="' . $this->getName() . '">' . $this->getLabel() . '</label>';
        }

        $field .= '<input type="' . $this->getType() . '" name="' . $this->getName() . '" id="' . $this->getName() . '"';

        if (!empty($options = $this->getOptions())) {
            foreach ($options as $key => $option) {
                $field .= "$key=$option ";
            }
        }

        if (!empty($this->getValue())) {
            $field .= ' value="' . htmlspecialchars($this->getValue()) . '"';
        }

        $field .= '/>';

        return $field;
    }
}