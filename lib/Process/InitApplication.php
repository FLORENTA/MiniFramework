<?php

namespace Lib\Process;

use Lib\DependencyInjection\ClassBuilder;
use Lib\DependencyInjection\ContainerInterface;
use Lib\DependencyInjection\DependencyInjection;
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
     * Finding all the classes and their dependencies required to run the application
     * Passing them to the container
     * Instantiating Application to run the application [ see constructor ]
     */
    public function start()
    {
        $this->session  = new Session;
        $this->logger   = new Logger();
        $this->response = new Response($this->session);

        try {
            /* Loading classes to instantiate automatically */
            /** @var array $parameters */
            $parameters = (new DependencyInjection)->getParameters();

            /** @var ContainerInterface $container */
            $container = new ClassBuilder($parameters);

            $this->templating = $container->get('templating');

            $container->get('event.dispatcher')->registerEventListeners($parameters);

            $container->get('session')->set('start', microtime(true));

            new Application($container, $parameters);

        } catch (\Exception $exception) {
            var_dump($exception->getMessage());die;
            $this->logger->error($exception->getMessage());
            $content = $this->templating->render('404', [
                'error' => $exception->getMessage()
            ]);

            $this->response->setContent($content);
            $this->response->send();
        }
    }
}