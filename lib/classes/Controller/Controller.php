<?php

namespace Classes\Controller;

use Classes\DependencyInjection\Container;
use Classes\DependencyInjection\ContainerInterface;
use Classes\Form\Form;
use Classes\Form\FormBuilder;
use Classes\Http\Request;
use Classes\Http\Response;
use Classes\Http\Session;
use Classes\Model\Orm\EntityManager;
use Entity\User;

/**
 * Class Controller
 * @package Classes
 */
abstract class Controller
{
    /** @var Container $container */
    protected $container;

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
        return $this->container->get('manager');
    }

    /** @return Response */
    public function getResponse()
    {
        return $this->container->get('response');
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
     * @param string $content
     * @param string $status
     */
    public function send($content, $status = Response::SUCCESS)
    {
        $this->getResponse()->send($content, $status);
    }

    /**
     * @param string $content
     * @param string $status
     */
    public function sendJson($content, $status = Response::SUCCESS)
    {
        $this->getResponse()->sendJson($content, $status);
    }

    /**
     * @param $template
     * @param array $parameters
     * @return false|string
     */
    public function render($template, array $parameters = [])
    {
        return $this->getResponse()->render($template, $parameters);
    }

    /** @param null $route */
    public function redirectToRoute($route = null)
    {
        $this->getResponse()->redirectToRoute($route);
    }

    /**
     * @param string $form
     * @param null $entity
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
     * @return null|string
     */
    public function getParameter($parameter)
    {
        return $this->container->getParameter($parameter);
    }

    /**
     * @param string $parameters
     * @return bool
     */
    public function hasParameter($parameters)
    {
        return $this->container->hasParameter($parameters);
    }
}