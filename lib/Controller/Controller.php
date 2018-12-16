<?php

namespace Lib\Controller;

use http\Exception\InvalidArgumentException;
use Lib\DependencyInjection\Container;
use Lib\DependencyInjection\ContainerInterface;
use Lib\Form\FormBuilder;
use Lib\Form\FormInterface;
use Lib\Http\RedirectResponse;
use Lib\Http\Request;
use Lib\Http\Response;
use Lib\Http\Session;
use Lib\Model\Orm\EntityManager;
use Entity\User;
use Lib\Routing\Router;
use Lib\Throwable\Response\RenderException;
use Lib\Throwable\Routing\NoRouteFoundException;
use Lib\Templating\Template;
use Lib\Utils\Logger;

/**
 * Class Controller
 * @package Lib
 */
abstract class Controller
{
    /** @var Container $container */
    private $container;

    /**
     * Controller constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return Logger|null
     */
    public function getLogger()
    {
        return $this->container->get('logger');
    }

    /**
     * @return EntityManager|null
     */
    public function getManager()
    {
        return $this->container->get('entity.manager');
    }

    /**
     * @return Response|null
     */
    public function getResponse()
    {
        return $this->container->get('response');
    }

    /**
     * @return Template|null
     */
    public function getTemplating()
    {
        return $this->container->get('templating');
    }

    /**
     * @return Request|null
     */
    public function getRequest()
    {
        return $this->container->get('request');
    }

    /**
     * @return Session|null
     */
    public function getSession()
    {
        return $this->container->get('session');
    }

    /**
     * @return User|null
     */
    public function getUser()
    {
        return $this->getSession()->get('user');
    }

    /**
     * @param $template
     * @param array $parameters
     *
     * @return Response
     */
    public function render($template, array $parameters = [])
    {
        /** @var Template $templating */
        $templating = $this->container->get('templating');
        try {
            return $templating->render($template, $parameters);
        } catch (RenderException $renderException) {
            ob_end_clean(); // Clean buffer
            $this->getLogger()->error($renderException->getMessage(), [
                '_METHOD_' => __METHOD__
            ]);
            return $this->render('404', [
                'error' => $this->getParameter('renderErrorMessage')
            ]);
        }
    }

    /**
     * @param null $route
     *
     * @return RedirectResponse|Response
     */
    public function redirectToRoute($route = null)
    {
        try {
            return new RedirectResponse($route);
        } catch (NoRouteFoundException $noRouteFoundException) {
            $this->getLogger()->error($noRouteFoundException->getMessage(), [
                '_controller' => get_called_class(),
                '_Exception' => NoRouteFoundException::class
            ]);

            return $this->render('404', [
                'message' => $noRouteFoundException->getMessage()
            ]);
        }
    }

    /**
     * @param string $form
     * @param null $entity
     *
     * @param array $options
     * @return FormInterface
     */
    public function createForm($form, $entity = null, $options = [])
    {
        /** @var FormBuilder $formBuilder */
        $formBuilder = $this->container->get('form.builder');

        return $formBuilder->createForm($form, $entity, $this->getRequest(), $options);
    }

    /**
     * @param string $parameter
     *
     * @return null|string
     */
    public function getParameter($parameter)
    {
        return $this->container->getParameter($parameter);
    }

    /**
     * @param string $parameters
     *
     * @return bool
     */
    public function hasParameter($parameters)
    {
        return $this->container->hasParameter($parameters);
    }

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->container->get('router');
    }

    /**
     * @param null $route
     * @return string
     */
    public function createUrl($route = null)
    {
        return $this->getRouter()->createUrl($route);
    }
}