<?php

namespace Classes\Utils;

/**
 * Class Hydrator
 * @package Classes
 */
abstract class Hydrator
{
    /**
     * Function to build the form nodes
     * @param array $parameters
     */
    public function hydrate(array $parameters = [])
    {
        foreach ($parameters as $key => $value) {
            if (method_exists($this, $method = "set" . ucfirst($key)) &&
                is_callable([$this, $method])) {
                $this->$method($value);
            }
        }
    }
}