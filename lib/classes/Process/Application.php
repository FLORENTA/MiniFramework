<?php

namespace Classes\Process;

use Classes\Controller\Controller;
use Classes\DependencyInjection\ContainerInterface;
use Classes\Http\Response;
use Classes\Routing\Router;
use Classes\Utils\Message;

date_default_timezone_set("Europe/Paris");

/**
 * Class Application
 * @package Classes
 */
class Application
{
    /**
     * @var ContainerInterface $container
     */
    protected $container;

    /**
     * @var array $parameters
     */
    protected $parameters;

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
     *
     * @throws \Exception
     */
    public function run()
    {
        /** @var Router $router */
        $router = $this->container->get('router');

        $controller = $router->getController();

        if (!$controller) {
            /** @var Response $response */
            $response = $this->container->get('response');
            return $response->redirectToRoute('app_login');
        }

        $controller = new $controller($this->container);

        $action = $router->getAction();

        $reflectionMethod = new \ReflectionMethod($controller, $action);

        /* Getting the target method needed parameters */
        $methodParameters = $reflectionMethod->getParameters();

        $arguments = [];

        foreach ($methodParameters as $methodParameter) {

            /* Getting the type of the parameter (object ? ...) */
            /* E.g: Classes\Request */
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
                call_user_func_array([$controller, $action], $arguments);
            }
        } catch (\Exception $exception) {
            if ($this->container->get('request')->isXMLHttpRequest()) {
                $controller->send(
                    Message::ERROR,
                    Response::SERVER_ERROR
                );
            }

            throw $exception;
        }
    }
}