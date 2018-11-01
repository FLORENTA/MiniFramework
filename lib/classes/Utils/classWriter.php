<?php

namespace Classes\Utils;

/**
 * Class ClassWriter
 * @package Classes\Utils
 */
class ClassWriter
{
    /** @var string $context */
    private $context = '$this->';

    /** @var string $content */
    private $content;

    /**
     * @param string $arg
     * @param string $type
     */
    public function addAttribute($arg, $type)
    {
        $attribute = "";
        $this->content .= $attribute;
    }

    public function addLineBreak()
    {
        $this->content .= "\n";
    }

    /**
     * @param string $attribute
     * @param string $type
     */
    public function addSetter($attribute, $type)
    {
        $method = 'set'.ucfirst($attribute);

        $setter = "     /**\n".
                  "      * @param $type $$attribute\n".
                  "      */\n".
                  "     public function $method($$attribute)\n".
                  "     {\n".
                  "         {$this->context}$attribute = $$attribute;\n".
                  "     }\n";

        $this->content .= $setter;
    }

    /**
     * @param string $attribute
     * @param string $type
     */
    public function addGetter($attribute, $type)
    {
        $method = 'get'.ucfirst($attribute);

        $getter = "     /**\n".
                  "      * @return $type\n".
                  "      */\n".
                  "     public function $method()\n".
                  "     {\n".
                  "         return {$this->context}$attribute;\n".
                  "     }\n";

        $this->content .= $getter;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }
}