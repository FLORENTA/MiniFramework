<?php

namespace Lib\Controller;

use Lib\DependencyInjection\ContainerInterface;
use Lib\Model\Orm\EntityManager;

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
     *
     * @param ContainerInterface $container
     * @param array $classes
     */
    public function __construct(ContainerInterface $container, $classes)
    {
        $this->container = $container;
        $this->classes   = $classes;
    }

    /**
     * @param \ReflectionParameter[] $methodParameters
     * @param array $vars
     * @param array $paramOrder
     * @return array
     * @throws \ReflectionException
     */
    public function getControllerMethodArguments(
        $methodParameters = [],
        $vars = [],
        $paramOrder = []
    )
    {
        $arguments = [];

        foreach ($methodParameters as $key => $methodParameter) {
            $isClass = true;

            /* Getting the type of the parameter (object ? ...) */
            /* E.g: Lib\Request */
            if (null !== $methodParameter->getClass()) {
                /** @var string $parameterType */
                $parameterType = $methodParameter->getClass()->getName();
            } else {
                /** @var string $parameterType */
                $parameterType = $methodParameter->getName();
                $isClass = false;
            }

            /* Finding the id of this class within the container registered classes */
            if (false !== ($id = array_search($parameterType, $this->classes))) {
                if ($this->container->has($id)) {
                    $arguments[] = $this->container->get($id);
                }
            } else {
                foreach ($paramOrder as $k => &$p) {
                    if (isset($vars[$p])) {
                        $field = $p;
                        $value = $vars[$p][0];
                        unset($vars[$p][0]);
                        if (empty($vars[$p])) {
                            unset($vars[$p]);
                        } else {
                            $vars[$p] = array_values($vars[$p]);
                        }
                        unset($paramOrder[$k]);
                        break;
                    }
                }

                if ($isClass) {
                    if (preg_match('/Entity/', (new \ReflectionClass($parameterType))->getNamespaceName())) {
                        /** @var EntityManager $em */
                        $em = $this->container->get('entity.manager');
                        $arguments[] = $em->getEntityModel($parameterType)->findOneBy([$field => $value]);
                    }
                } else {
                    $arguments[] = $value;
                }
            }
        }

        return $arguments;
    }
}