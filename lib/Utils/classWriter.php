<?php

namespace Lib\Utils;

use Lib\Model\Relation\RelationType;

/**
 * Class ClassWriter
 * @package Lib\Utils
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
        $this->content .= "class $className\n{\n";

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

    /**
     * @return string
     */
    public function getContent()
    {
        $content = $this->content . "}";
        // Clear the content variable for next entities if generation
        // corresponds to all entities at once
        $this->content = '';
        return $content;
    }

    public function addOneToOneRelation(
        $relationField,
        $targetClass,
        $mappedBy = null,
        $inversedBy = null,
        $joinColumn = null
    )
    {
        $this->content .= "    /**\n" .
                          "     * @oneToOne(target=$targetClass,";

        if (!is_null($mappedBy)) {
            $this->content .= " mappedBy=$mappedBy)\n";
        }

        if (!is_null($inversedBy)) {
            $this->content .= " inversedBy=$inversedBy)\n";
        }

        if (!is_null($joinColumn)) {
            $this->content .= "     * @joinColumn(name="."$joinColumn".")\n";
        }


        $this->content .= "     */\n".
                          "    private $$relationField;\n\n";

        $type = str_replace('Entity\\', '', $targetClass);

        $this->addSetter($relationField, $type)
             ->addGetter($relationField, $type);
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

        $type = str_replace('Entity\\', '', $targetClass);

        // Common function to 'oneToMany' and 'manyToMany' relations
        $this->addManyTypeRelationSetterAndGetter($relationField, $type);
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

        $type = str_replace('Entity\\', '', $targetClass);

        $this->addSetter($relationField, $type);
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
            $this->content .= "     * @joinTable(name="."$joinTable".")\n";
        }


        $this->content .= "     */\n".
                          "    private $$relationField = [];\n\n";

        $entity = str_replace('Entity\\', '', $targetClass);

        // Common function to 'oneToMany' and 'manyToMany' relations
        $this->addManyTypeRelationSetterAndGetter($relationField, $entity);
    }

    /**
     * Concerns oneToMany && manyToMany relations
     *
     * @param string $relationField
     * @param string $type
     * @return $this
     */
    private function addManyTypeRelationSetterAndGetter(
        $relationField,
        $type
    )
    {
        $setMethod = 'add';
        $getMethod = 'get';
        $argument = '';

        // If $relationField = images_test => [argument = imagesTest, method = addImagesTest
        // If $relationField = images_tests => [argument = imagesTest, method = addImagesTest
        // If $relationField = images_testz => [argument = imagesTestz, method = addImagesTestz
        // If $relationField = images => [argument = images, method = addImage

        /**
         * @var string $word
         * @return string
         */
        $transformEndOfWord = function($word) {
            switch ($word) {
                case substr($word, -3) === 'ies':
                    $word = str_replace('ies', 'y', $word);
                    break;
                case substr($word, -1) === 's':
                    $word = substr($word, 0, -1);
                    break;
            }
            return $word;
        };

        // If the relation attribute is composed of several words separated by '_'
        if (preg_match('/_/', $relationField)) {
            $words = explode('_', $relationField);
            $nbOfWords = count($words);

            array_map(function($word, $key) use (
                $nbOfWords, $transformEndOfWord, &$setMethod, &$getMethod, &$argument) {

                $getMethod .= ucfirst($word);

                if ($key === ($nbOfWords - 1)) {
                    // Removing/Replacing letter only for last-word setter
                    // if string matches a certain pattern
                    // E.g => [dummies : dummy, images : image, testz : testz]
                    $word = $transformEndOfWord($word);
                }

                // key 0 = first word of words separated by an '_'
                // If key > 0, capitalize the first word letter
                $argument .= $key === 0 ? $word : ucfirst($word);

                // $word may have been transformed (see condition above)
                $setMethod .= ucfirst($word);

            }, $words, array_keys($words));
        } else {
            // E.g : getImages
            $getMethod .= ucfirst($relationField);
            // Removing/Replacing letter only for setter
            // if string matches a certain pattern
            // E.g : images => $image
            $funcArg = $transformEndOfWord($relationField);
            $argument .= $funcArg;
            $setMethod .= ucfirst($funcArg);
        }

        $setter = "    /**\n".
                  "     * @param $type $$argument\n".
                  "     *\n".
                  "     * @return {$this->context}\n".
                  "     */\n".
                  "    public function $setMethod($$argument)\n".
                  "    {\n".
                  "        {$this->context}->{$relationField}[] = $$argument;\n\n".
                  "        return {$this->context};\n".
                  "    }\n\n";

        $getter = "    /**\n".
                  "     * @return array\n".
                  "     */\n".
                  "    public function $getMethod()\n".
                  "    {\n".
                  "        return {$this->context}->$relationField;\n".
                  "    }\n\n";

        $this->content .= $setter;
        $this->content .= $getter;

        return $this;
    }
}