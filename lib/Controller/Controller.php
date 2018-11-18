<?php

namespace Lib\Controller;

use Lib\DependencyInjection\Container;
use Lib\DependencyInjection\ContainerInterface;
use Lib\Form\Form;
use Lib\Form\FormBuilder;
use Lib\Http\Request;
use Lib\Http\Response;
use Lib\Http\Session;
use Lib\Model\Orm\EntityManager;
use Entity\User;
use Lib\Templating\Template;
use Lib\Utils\Message;

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

    /** @return EntityManager */
    public function getManager()
    {
        return $this->container->get('entity.manager');
    }

    /** @return Response */
    public function getResponse()
    {
        return $this->container->get('response');
    }

    /** @return Template */
    public function getTemplating()
    {
        return $this->container->get('templating');
    }

    /** @return Request */
    public function getRequest()
    {
        return $this->container->get('request');
    }

    /** @return Session */
    public function getSession()
    {
        return $this->container->get('session');
    }

    /** @return User */
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
        return $templating->render($template, $parameters);
    }

    /**
     * @param null $route
     *
     * @return Response
     */
    public function redirectToRoute($route = null)
    {
        if (is_null($route)) {
            header('location:' . $_SERVER['HTTP_REFERER']);
            exit; // exit otherwise, the session key will be unset before using it !!
        }

        /* In case of problem during route collection build */
        /* Not possible to match the $route value against a set of routes as do not exist */
        $routeFound = false;

        /* The route name may be given */
        if (isset($GLOBALS['routes'][$route])) {
            $route = $GLOBALS['routes'][$route];
            $routeFound = true;
        }

        if (!$routeFound) {
            return $this->render('404', [
                'error' => Message::ERROR
            ]);
        }

        $host = $_SERVER['HTTP_HOST'];

        $scriptName = str_replace(
            '/app.php',
            '',
            $_SERVER['SCRIPT_NAME']
        );

        $route = $host . $scriptName . $route;

        header("Location: http://$route");
        exit;
    }

    /**
     * @param string $form
     * @param null $entity
     *
     * @return Form
     */
    public function createForm($form, $entity = null)
    {
        /** @var FormBuilder $formBuilder */
        $formBuilder = $this->container->get('form.builder');

        $formBuilder->createForm($form, $entity);

        /* Return the object */
        $form = $formBuilder->getForm();

        return $form;
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
}