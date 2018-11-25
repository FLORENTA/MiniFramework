<?php

namespace Lib\Process;

use Lib\Controller\Controller;
use Lib\DependencyInjection\ContainerInterface;
use Lib\Event\EventDispatcher;
use Lib\Exception\ExceptionEvent;
use Lib\Http\Response;
use Lib\Model\JsonResponse;
use Lib\Routing\Router;

date_default_timezone_set("Europe/Paris");

/**
 * Class Application
 * @package Lib
 */
class Application
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var EventDispatcher $eventDispatcher */
    private $eventDispatcher;

    /** @var array $parameters */
    private $parameters;

    /**
     * Application constructor.
     *
     * Find the controller corresponding to the target url
     * Find the action to call in the target controller
     * Call the method of the controller with the arguments needed
     *
     * @param ContainerInterface $container
     * @param array $parameters
     * @throws \Exception
     */
    public function __construct(ContainerInterface $container, $parameters)
    {
        $this->container       = $container;
        $this->eventDispatcher = $container->get('event.dispatcher');
        $this->parameters      = $parameters;
    }

    /**
     * @return Response|JsonResponse
     */
    public function run()
    {
        try {
            /** @var Router $router */
            $router = $this->container->get('router');

            $router->setRoutes();

            /** @var Controller $controller */
            $controller = $router->getController();

            /** @var Controller $controller */
            $controller = new $controller($this->container);

            /** @var string $action */
            $action = $router->getAction();

            $reflectionMethod = new \ReflectionMethod($controller, $action);

            /* Getting the target method needed parameters */
            $methodParameters = $reflectionMethod->getParameters();

            /** @var array $arguments of the controller action to call */
            $arguments = $this->getControllerMethodArguments($methodParameters);

            // An error may happen in the controller
            try {
                // call the controller method with the needed arguments
                // returns Response|JsonResponse
                return \call_user_func_array(array($controller, $action), $arguments);
            } catch (\Exception $exception) {
                throw $exception;
            }
        } catch (\Exception $exception) {
            /** @var ExceptionEvent $exceptionEvent */
            $exceptionEvent = new ExceptionEvent($exception);
            // Dispatch to Lib\Exception\ExceptionListener
            $this->eventDispatcher->dispatch('exception', $exceptionEvent);
            return $exceptionEvent->getResponse();
        }
    }

    /**
     * @param array $methodParameters
     * @return array
     */
    private function getControllerMethodArguments($methodParameters)
    {
        $arguments = [];

        foreach ($methodParameters as $methodParameter) {
            /* Getting the type of the parameter (object ? ...) */
            /* E.g: Lib\Request */
            /** @var string $parameterType */
            $parameterType = $methodParameter->getClass()->getName();
            /* Finding the id of this class within the container registered classes */
            foreach ($this->parameters as $id => $datas) {
                /* Comparing the class with all the classes registered in classes.yml */
                if ($datas['class'] === $parameterType) {
                    // Storing the object corresponding to the found Id (thanks to class comparison)
                    $arguments[] = $this->container->get($id);
                    break;
                }
            }
        }

        return $arguments;
    }
}