<?php

namespace Lib\Process;

use Lib\DependencyInjection\ClassBuilder;
use Lib\DependencyInjection\ContainerInterface;
use Lib\DependencyInjection\DependencyInjection;
use Lib\Event\EventDispatcher;
use Lib\Http\Response;
use Lib\Http\Session;
use Lib\Model\JsonResponse;
use Lib\Templating\Template;
use Lib\Utils\Message;

/**
 * Class InitApplication
 * @package Lib
 */
class InitApplication
{
    /**
     * Finding all the classes and their dependencies
     * to run the application
     * Storing them into the container
     *
     * @return Response|JsonResponse
     */
    public function start()
    {
        try {
            /* Loading classes to instantiate automatically */
            /** @var DependencyInjection $dependencyInjection */
            $dependencyInjection = new DependencyInjection;

            /** @var array $parameters */
            $parameters = $dependencyInjection->getParameters();

            /** @var array $events */
            $events = $dependencyInjection->getEvents();

            /** @var ContainerInterface $container */
            $container = new ClassBuilder($parameters);

            /** @var EventDispatcher $eventDispatcher */
            $eventDispatcher = $container->get('event.dispatcher');

            // Registering event listeners if some
            $eventDispatcher->registerEvents($events);

            /** @var Session $session */
            $session = $container->get('session');
            $session->set('start', microtime(true));

            return (new Application($container, $parameters))->run();

        } catch (\Exception $exception) {
            // For exceptions happening when building container
            $response = (new Template())->render('404', [
                'error' => Message::ERROR
            ]);
            return $response;
        }
    }
}