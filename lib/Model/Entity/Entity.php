<?php

namespace Lib\Model\Entity;

/**
 * Class Entity
 * @package Lib\Model\Entity
 */
abstract class Entity implements \JsonSerializable
{
    /** @var array $properties */
    protected $properties = [];

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function jsonSerialize()
    {
        $reflectionProperties = (new \ReflectionClass(get_called_class()))->getProperties();

        foreach ($reflectionProperties as $reflectionProperty) {

            $name = $reflectionProperty->getName();

            if ($name !== 'properties') {
                $this->properties[$name] = $this->$name;
            }
        }

        return $this->properties;
    }
}