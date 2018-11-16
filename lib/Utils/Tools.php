<?php

namespace Lib\Utils;

/**
 * Class Tools
 * @package Lib\Utils
 */
class Tools
{
    /**
     * Method to split a camel cased word into words separated by '_'
     * The result is then lower cased
     *
     * E.g : columnName will return column_name
     * id will return id
     * 
     * @param string $word
     * @return string
     */
    public static function splitCamelCasedWords($word)
    {
        $words = preg_split('/(?=[A-Z])/', $word);

        return strtolower(implode('_', $words));
    }

    /**
     * @param string $word
     * @return bool|mixed|string
     */
    public static function TransformEndOfWord($word)
    {
        switch ($word) {
            case substr($word, -3) === 'ies':
                $word = str_replace('ies', 'y', $word);
                break;
            case substr($word, -1) === 's':
                $word = substr($word, 0, -1);
                break;
        }

        return $word;
    }
}