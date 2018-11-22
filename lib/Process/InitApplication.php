<?php

namespace Lib\Process;

use Lib\DependencyInjection\ClassBuilder;
use Lib\DependencyInjection\ContainerInterface;
use Lib\DependencyInjection\DependencyInjection;
use Lib\Event\EventDispatcher;
use Lib\Http\Response;
use Lib\Http\Session;
use Lib\Templating\Template;
use Lib\Utils\Logger;

/**
 * Class InitApplication
 * @package Lib
 */
class InitApplication
{
    /** @var Logger $logger */
    private $logger;

    /** @var Session $session */
    private $session;

    /** @var Response $response */
    private $response;

    /** @var Template $templating */
    private $templating;

    /**
     * InitApplication constructor.
     *
     * Finding all the classes and their dependencies required to run the application
     * Passing them to the container
     * Instantiating Application to run the application [ see constructor ]
     * @throws \Exception
     */
    public function __construct()
    {
        $this->session  = new Session;
        $this->logger   = new Logger;
        $this->response = new Response($this->session);

        try {
            /* Loading classes to instantiate automatically */
            /** @var array $parameters */
            $parameters = (new DependencyInjection)->getParameters();

            /** @var ContainerInterface $container */
            $container = new ClassBuilder($parameters);

            $this->templating = $container->get('templating');

            /** @var EventDispatcher $evDispatcher */
            $evDispatcher = $container->get('event.dispatcher');

            // Registering event listeners if some
            $evDispatcher->registerEventListeners($parameters);

            $this->session->set('start', microtime(true));

            new Application($container, $parameters);

        } catch (\Exception $exception) {
            throw $exception;
        }
    }
}