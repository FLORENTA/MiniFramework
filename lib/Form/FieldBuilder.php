<?php

namespace Lib\Form;

/**
 * Class FieldBuilder
 * @package Lib\Form
 */
class FieldBuilder
{
    /** @var string $widget */
    private $widget = '';

    /** @var Field $field */
    private $field;

    /**
     * FieldBuilder constructor.
     * @param Field $field
     */
    public function __construct(Field $field)
    {
        $this->field = $field;
    }

    // Function to init the field creation
    public function create()
    {
        $this->createLabel()->createWidget();
        $this->field->setCreated(true);
    }

    /**
     * @return $this
     */
    public function createLabel()
    {
        if (false !== $this->field->getLabel()) {
            $this->widget .= '<div><label for="' . $this->field->getName() . '">' . $this->field->getLabel() . '</label>';
        }

        return $this;
    }

    private function createWidget()
    {
        switch ($this->field->getType()) {
            case 'select':
                $this->createSelect();
                break;
            case 'textarea':
                $this->createTextArea();
                break;
            default:
                $this->createInput();
                break;
        }
    }

    private function createInput()
    {
        $this->widget .= "<input ";
        $this->addType()->addName()->addId()->addOptions()->addValue();
        $this->widget .= " /></div>";
    }

    /**
     * @return $this
     */
    private function addType()
    {
        $this->widget .= "type='" . $this->field->getType() . "'";

        return $this;
    }

    /**
     * @return $this
     */
    private function addName()
    {
        $this->widget .= " name='" . $this->field->getName() . "'";

        return $this;
    }

    /**
     * @return $this
     */
    private function addId()
    {
        $this->widget .= " id='" . $this->field->getName() . "'";

        return $this;
    }

    /**
     * @return $this
     */
    private function addOptions()
    {
        if (!empty($options = $this->field->getOptions())) {
            foreach ($options as $key => $option) {
                $this->widget .= " $key=$option ";
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function addValue()
    {
        if (!empty($this->field->getValue())) {
            $this->widget .= " value=" . htmlspecialchars($this->field->getValue()) . "";
        }

        return $this;
    }

    private function createTextArea()
    {
        $this->widget .= '<textarea>';

        if (!empty($this->field->getValue())) {
            $this->widget .= $this->field->getValue();
        }

        $this->widget .= '</textarea>';
    }

    private function createSelect()
    {
        $this->widget .= "<select name='{$this->field->getName()}' id='{$this->field->getName()}'>";

        $this->widget .= "</select>";
    }

    /**
     * @return string
     */
    public function getWidget()
    {
        return $this->widget;
    }
}