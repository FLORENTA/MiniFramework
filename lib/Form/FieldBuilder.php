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

    /**
     * Function to init the field creation
     *
     * @param array $choices
     */
    public function create($choices = [])
    {
        $this->createLabel()->createWidget($choices);
        $this->field->setCreated(true);
    }

    public function update()
    {
        switch ($this->field->getType()) {
            case 'select':
                $this->addValue()->finishSelect();
                break;
            case 'textarea':
                $this->finishTextarea();
                break;
            default:
                $this->widget = substr($this->widget, 0, -9);
                $this->addValue()->finishInput();
                break;
        }

        $this->finishWidget();
    }

    /**
     * @return $this
     */
    public function createLabel()
    {
        if (false !== $this->field->getLabel()) {
            $this->widget .= '<div><label for=' . $this->field->getName() . '>' . $this->field->getLabel() . '</label>';
        }

        return $this;
    }

    /**
     * @param array $choices
     */
    private function createWidget($choices = [])
    {
        switch ($this->field->getType()) {
            case 'select':
                $this->createSelect($choices);
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
        $this->widget .= '<input ';
        $this->addType()->addName()->addId()->addOptions()->addValue();
        $this->finishInput()->finishWidget();
    }

    /**
     * @return $this
     */
    private function finishInput()
    {
        $this->widget .= ' />';

        return $this;
    }

    /**
     * @return $this
     */
    private function finishSelect()
    {
        $this->widget .= "</select>";

        return $this;
    }

    private function finishWidget()
    {
        $this->widget .= "</div>";
    }

    /**
     * @return $this
     */
    private function addType()
    {
        $this->widget .= ' type=' . "{$this->field->getType()}" . '';

        return $this;
    }

    /**
     * @return $this
     */
    private function addName()
    {
        if (!is_null($this->field->getName())) {
            $this->widget .= ' name=' . $this->field->getName() . '';
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function addId()
    {
        if (!is_null($this->field->getName())) {
            $this->widget .= ' id=' . $this->field->getName() . '';
        }

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
            $this->widget .= ' value=' . htmlspecialchars($this->field->getValue()) . '';
        }

        if (!$this->field->isCollection() &&
            method_exists(
                $entity = $this->field->getParentForm()->getEntity(),
                $method = 'get' . ucfirst($this->field->getPreviousName())
            )
        ) {
            if (!empty($entity->$method())) {
                $this->widget .= ' value=' . htmlspecialchars($entity->$method());
            }
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

    /**
     * @param array $choices
     */
    private function createSelect($choices = [])
    {
        if (!empty($choices)) {
            $this->widget .= '<select ';
            $this->addName()->addId()->addOptions();
            $this->widget .= ' >';
            foreach ($choices as $choice) {
                $this->widget .= "<option value='$choice[0]'>$choice[0]</option>";
            }
            $this->finishSelect()->finishWidget();
        }
    }

    /**
     * @return string
     */
    public function getWidget()
    {
        return $this->widget;
    }
}