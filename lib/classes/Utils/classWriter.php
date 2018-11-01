<?php

namespace Classes\Utils;

/**
 * Class ClassWriter
 * @package Classes\Utils
 */
class ClassWriter
{
    /** @var string $context */
    private $context = '$this';

    /** @var string $content */
    private $content;

    /**
     * @return $this
     */
    public function initFile()
    {
        $this->content .= "<?php\n\n";

        return $this;
    }

    /**
     * @param null $ns
     * @return $this
     */
    public function addNamespace($ns = null)
    {
        if (is_null($ns)) {
            $this->content .= "namespace Entity;\n\n";
        } else {
            $this->content .= $ns . ";\n\n";
        }

        return $this;
    }

    /**
     * @param string $className
     * @return $this
     */
    public function addClass($className)
    {
        $this->content .= "class $className\n{";

        $this->addLineBreak();

        return $this;
    }

    /**
     * @param string $attribute
     * @param string $type
     * @return $this
     */
    public function addAttribute($attribute, $type)
    {
        $attribute = "    /**\n".
                     "     * @var $type $$attribute\n".
                     "     */\n".
                     "    private $$attribute;\n\n";

        $this->content .= $attribute;

        return $this;
    }

    /**
     * @param string $target
     * @param $setterParam
     * @param $getterParam
     * @param string $attributeCompletion
     */
    public function addSettersAndGetters(
        $target,
        $setterParam,
        $getterParam,
        $attributeCompletion = ''
    )
    {
        $this->addSetter($target, $setterParam, $attributeCompletion)
             ->addGetter($target, $getterParam);
    }

    /**
     * @param string $attribute
     * @param string $type
     * @param string $attributeCompletion
     * @return $this
     */
    public function addSetter($attribute, $type, $attributeCompletion = '')
    {
        $method = 'set'.ucfirst($attribute);

        $setter = "    /**\n".
                  "     * @param $type $$attribute\n".
                  "     *\n".
                  "     * @return {$this->context}\n".
                  "     */\n".
                  "    public function $method($$attribute)\n".
                  "    {\n".
                  "        {$this->context}->$attribute$attributeCompletion = $$attribute;\n\n".
                  "        return {$this->context};\n".
                  "    }\n\n";

        $this->content .= $setter;

        return $this;
    }

    /**
     * @param $attribute
     * @param $type
     * @return $this
     */
    public function addGetter($attribute, $type)
    {
        $method = 'get'.ucfirst($attribute);

        $getter = "    /**\n".
                  "     * @return $type\n".
                  "     */\n".
                  "    public function $method()\n".
                  "    {\n".
                  "        return {$this->context}->$attribute;\n".
                  "    }\n\n";

        $this->content .= $getter;

        return $this;
    }

    private function addLineBreak()
    {
        $this->content .= "\n";
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content . "}";
    }

    /**
     * @param string $relationField
     * @param string $targetClass
     * @param string $mappedBy
     */
    public function addOneToManyRelation($relationField, $targetClass, $mappedBy)
    {
        $this->content .= "    /**\n".
                          "     * @oneToMany(target=$targetClass, mappedBy=$mappedBy)\n".
                          "     */\n".
                          "    private $$relationField = [];\n\n";

        $entity = str_replace('Entity\\', '', $targetClass);

        $this->addManyTypeRelationSetter(
            $relationField,
            strtolower($entity),
            $entity
        );

        $this->addGetter($relationField, 'array');
    }

    /**
     * @param string $relationField
     * @param string $targetClass
     * @param string $inversedBy
     */
    public function addManyToOneRelation($relationField, $targetClass, $inversedBy)
    {
        $this->content .= "    /**\n".
                          "     * @manyToOne(target=$targetClass, inversedBy=$inversedBy)\n".
                          "     */\n".
                          "    private $$relationField;\n\n";


        $this->addSetter(
            $relationField,
            str_replace('Entity\\', '', $targetClass)
        );

        $this->addGetter($relationField, 'array');
    }

    /**
     * @param string $relationField
     * @param string $targetClass
     * @param null $mappedBy
     * @param null $inversedBy
     * @param null $joinTable
     */
    public function addManyToManyRelation(
        $relationField,
        $targetClass,
        $mappedBy = null,
        $inversedBy = null,
        $joinTable = null
    )
    {
        $this->content .= "    /**\n" .
                          "     * @manyToMany(target=$targetClass,";

        if (!is_null($mappedBy)) {
            $this->content .= " mappedBy=$mappedBy)\n";
        }

        if (!is_null($inversedBy)) {
            $this->content .= " inversedBy=$inversedBy)\n";
        }

        if (!is_null($joinTable)) {
            $this->content .= "     * @joinTable=(name=$joinTable)\n";
        }


        $this->content .= "     */\n".
                          "    private $$relationField = [];\n\n";

        $entity = str_replace('Entity\\', '', $targetClass);

        $this->addManyTypeRelationSetter(
            $relationField,
            strtolower($entity),
            $entity
        );

        $this->addGetter($relationField, 'array');
    }

    private function addManyTypeRelationSetter(
        $attribute,
        $argument,
        $type
    )
    {
        $method = 'add'.ucfirst($argument);

        $setter = "    /**\n".
            "     * @param $type $$argument\n".
            "     *\n".
            "     * @return {$this->context}\n".
            "     */\n".
            "    public function $method($$argument)\n".
            "    {\n".
            "        {$this->context}->{$attribute}[] = $$argument;\n\n".
            "        return {$this->context};\n".
            "    }\n\n";

        $this->content .= $setter;

        return $this;
    }
}