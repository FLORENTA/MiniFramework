<?php

namespace Classes\Form;

use Classes\Utils\Hydrator;

/**
 * Class Field
 * @package Classes
 */
class Field extends Hydrator
{
    protected $parameters;
    protected $label;
    protected $type;
    protected $name;
    protected $value;
    protected $options;

    /**
     * Field constructor.
     * Parameters contains label, input type ...
     * @param array $parameters
     */
    public function __construct(array $parameters = [])
    {
        $this->hydrate($parameters);
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