<?php

namespace Classes\Utils;

/**
 * Class Tools
 * @package Classes\Utils
 */
class Tools
{
    /**
     * Method to split a camel cased word into words separated by '_'
     * The result is then lower cased
     *
     * E.g : columnName will be transformed to column_name
     * 
     * @param string $word
     * @return string
     */
    public static function splitCamelCasedWords($word)
    {
        $words = preg_split('/(?=[A-Z])/', $word);
        $word = implode('_', $words);
        return strtolower($word);
    }
}