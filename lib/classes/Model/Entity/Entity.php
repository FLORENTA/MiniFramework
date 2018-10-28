<?php

namespace Classes\Model\Entity;

/**
 * Class Entity
 * @package Classes\Model\Entity
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