<?php

namespace Lib\Process;

use Lib\Controller\Controller;
use Lib\Controller\ControllerArgumentsManager;
use Lib\DependencyInjection\ContainerInterface;
use Lib\Event\EventDispatcher;
use Lib\Http\RedirectResponse;
use Lib\Throwable\ThrowableEvent;
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
     * @return Response|JsonResponse|RedirectResponse
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

            /* Getting the target controller method required parameters */
            $methodParameters = $reflectionMethod->getParameters();

            /** @var ControllerArgumentsManager $controllerArgumentsManager */
            $controllerArgumentsManager = $this->container->get('controller.arguments_manager');

            /** @var array $arguments of the controller action to call */
            $arguments = $controllerArgumentsManager->getControllerMethodArguments(
                $methodParameters
            );

            // An error may happen in the controller
            // call the controller method with the needed arguments
            // returns Response|JsonResponse|RedirectResponse
            $response = \call_user_func_array(array($controller, $action), $arguments);

            if (!$response instanceof Response and
                !$response instanceof JsonResponse and
                !$response instanceof RedirectResponse) {

                throw new \TypeError(
                    sprintf(
                        "The returned value must be an instance of Response 
                        or JsonResponse or RedirectResponse, %s given.", gettype($response) === 'object' ?
                            get_class($response) : gettype($response)
                    )
                );
            }
            return $response;

        } catch (\Throwable $throwable) {
            /** @var ThrowableEvent $exceptionEvent */
            $throwableEvent = new ThrowableEvent($throwable);
            // Dispatch to Lib\Throwable\ThrowableListener
            $this->eventDispatcher->dispatch('throwable.event', $throwableEvent);
            return $throwableEvent->getResponse();
        }
    }
}