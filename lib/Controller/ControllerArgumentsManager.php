<?php

namespace Lib\Controller;

use Lib\DependencyInjection\ContainerInterface;

/**
 * Service to return a target controller method argument(s)
 * Called in Lib/Process/Application
 *
 * Class ControllerArgumentsManager
 * @package Lib\Controller
 */
class ControllerArgumentsManager
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var array $classes */
    private $classes;

    /**
     * ControllerArgumentsManager constructor.
     * @param ContainerInterface $container
     * @param array $classes
     */
    public function __construct(ContainerInterface $container, $classes)
    {
        $this->container = $container;
        $this->classes   = $classes;
    }

    /**
     * @param array $methodParameters
     * @return array
     */
    public function getControllerMethodArguments($methodParameters)
    {
        $arguments = [];

        foreach ($methodParameters as $methodParameter) {
            /* Getting the type of the parameter (object ? ...) */
            /* E.g: Lib\Request */
            /** @var string $parameterType */
            $parameterType = $methodParameter->getClass()->getName();

            /* Finding the id of this class within the container registered classes */
            if (false !== ($id = array_search($parameterType, $this->classes))) {
                if ($this->container->has($id)) {
                    $arguments[] = $this->container->get($id);
                }
            }
        }

        return $arguments;
    }
}