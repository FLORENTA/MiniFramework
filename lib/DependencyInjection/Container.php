<?php

namespace Lib\DependencyInjection;

/**
 * Class Container
 * @package Lib
 */
class Container implements ContainerInterface
{
    /** @var array $parameters */
    protected $parameters;

    /**
     * This array is filled by ClassBuilder that extends Container
     * @var array $arrayOfInstances
     */
    protected $arrayOfInstances = [];

    /**
     * @param string $id
     * @return object|null
     */
    public function get($id)
    {
        return $this->has($id)
            ? $this->arrayOfInstances[$id]
            : null;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        return isset($this->arrayOfInstances[$id]);
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getParameter($name)
    {
        return $this->parameters[$name];
    }

    /**
     * @param string$name
     * @return bool
     */
    public function hasParameter($name)
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new \InvalidArgumentException(
                sprintf('The parameter %s does not exist', $name)
            );
        }

        return true;
    }
}