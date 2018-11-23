<?php

namespace Lib\Process;

use Lib\Controller\Controller;
use Lib\DependencyInjection\ContainerInterface;
use Lib\Http\RedirectResponse;
use Lib\Http\Response;
use Lib\Model\JsonResponse;
use Lib\Exception\Security\AccessDeniedException;
use Lib\Exception\Routing\NoRouteFoundException;
use Lib\Routing\Router;
use Lib\Exception\Routing\RoutingException;
use Lib\Exception\Cache\CacheException;
use Lib\Utils\Logger;
use Lib\Utils\Message;

date_default_timezone_set("Europe/Paris");

/**
 * Class Application
 * @package Lib
 */
class Application
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var array $parameters */
    private $parameters;

    /** @var Logger $logger */
    private $logger;

    /**
     * Application constructor.
     * @param ContainerInterface $container
     * @param array $parameters
     * @throws \Exception
     */
    public function __construct(ContainerInterface $container, $parameters)
    {
        $this->container = $container;
        $this->parameters = $parameters;
        $this->logger = $container->get('logger');

        try {
            $this->run();
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * Find the controller corresponding to the target url
     * Find the action to call in the target controller
     * Call the method of the controller with the arguments needed
     * @throws \Exception
     */
    public function run()
    {
        try {
            /** @var Router $router */
            $router = $this->container->get('router');
        } catch (CacheException $cacheException) {
            throw new \Exception($cacheException->getMessage());
        }

        try {
            /** @var Controller $controller */
            $controller = $router->getController();
        } catch (AccessDeniedException $accessDeniedException) {
            try {
                return new RedirectResponse('app_login');
            } catch (NoRouteFoundException $noRouteFoundException) {
                throw new \Exception($noRouteFoundException->getMessage());
            }
        } catch (RoutingException $routerException) {
            throw new \Exception();
        } catch (\Exception $exception) {
            throw $exception;
        }

        /** @var Controller $controller */
        $controller = new $controller($this->container);

        /** @var string $action */
        $action = $router->getAction();

        $reflectionMethod = new \ReflectionMethod($controller, $action);

        /* Getting the target method needed parameters */
        $methodParameters = $reflectionMethod->getParameters();

        $arguments = [];

        foreach ($methodParameters as $methodParameter) {

            /* Getting the type of the parameter (object ? ...) */
            /* E.g: Lib\Request */
            /** @var string $parameterType */
            $parameterType = $methodParameter->getClass()->getName();
            /* Finding the id of this class within the container registered classes
               Indeed, calling container->get($id) to prevent
               useless new potential instantiation if the class has already
               been instantiated and thus stored within the reverseTree array
            */
            foreach ($this->parameters as $id => $datas) {
                /* Comparing the class with all the classes registered in classes.yml */
                if ($datas['class'] === $parameterType) {
                    // Storing the object corresponding to the found Id (thanks to class comparison)
                    $arguments[] = $this->container->get($id);
                    break;
                }
            }
        }

        // An error may happen in the controller
        try {
            // Might be string if view returned (exception thrown during execution)
            if ($controller instanceof Controller) {
                // call the controller method with the needed arguments
                /** @var Response|JsonResponse $response */
                $response = call_user_func_array(
                    [$controller, $action],
                    $arguments
                );

                $response->send();
            }
        } catch (\Exception $exception) {
            if ($this->container->get('request')->isXMLHttpRequest()) {
                $jsonResponse = new JsonResponse(
                    Message::ERROR,
                    Response::SERVER_ERROR
                );

                $jsonResponse->send();
            }

            throw $exception;
        }
    }
}