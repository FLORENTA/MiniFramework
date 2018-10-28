<?php

namespace Classes\Process;

use Classes\DependencyInjection\ClassBuilder;
use Classes\DependencyInjection\ContainerInterface;
use Classes\DependencyInjection\DependencyInjection;
use Classes\Http\Response;
use Classes\Http\Session;
use Classes\Utils\Logger;

/**
 * Class InitApplication
 * @package Classes
 */
class InitApplication
{
    /**
     * @var Logger $logger
     */
    private $logger;

    /**
     * @var Session $session
     */
    private $session;

    /**
     * @var Response $response
     */
    private $response;

    /**
     * Finding all the classes and their dependencies required to run the application
     * Passing them to the container
     * Instantiating Application to run the application [ see constructor ]
     */
    public function start()
    {
        $this->session = new Session;
        $this->logger = new Logger();
        $this->response = new Response($this->session, $this->logger);

        try {
            /* Loading classes to instantiate automatically */
            /** @var array $parameters */
            $parameters = (new DependencyInjection)->getParameters();

            /** @var ContainerInterface $container */
            $container = new ClassBuilder($parameters);

            $container->get('event.dispatcher')->registerEventListeners($parameters);

            $container->get('session')->set('start', microtime(true));

            new Application($container, $parameters);

        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->response->render('404', [
                'error' => $exception->getMessage()
            ]);
        }
    }
}