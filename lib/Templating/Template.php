<?php

namespace Lib\Templating;

use Lib\Http\Response;
use Lib\Http\Session;

/**
 * Class Template
 * @package Lib\Templating
 */
class Template
{
    /** @var Session $session */
    private $session;

    /** @var array $routes */
    private $routes = [];

    /** @var array $routeCollection */
    private $routeCollection = [];

    /** @var array $routeVars */
    private $routeVars = [];

    /**
     * Template constructor.
     * @param Session $session
     */
    public function __construct(Session $session = null)
    {
        $this->session = $session;
    }

    /**
     * @param string $template
     * @param array $parameters
     * @return Response
     */
    public function render($template, array $parameters = [])
    {
        $template = str_replace('.php', '', $template);

        $response = new Response;

        foreach ($parameters as $key => $value) {
            $$key = $value;
        }

        ob_start();
        include ROOT_DIR . '/src/Resources/views/templates/' . $template . '.php';
        // used in layout.php
        $content = ob_get_clean();

        ob_start();
        include ROOT_DIR . '/src/Resources/views/layout.php';
        $content = ob_get_clean();

        $response->setContent($content);

        return $response;
    }

    /**
     * getcwd() = web
     * @param string $filename
     * @return mixed
     */
    public function asset($filename)
    {
        $extension = explode('.', $filename)[1];
        return $file = str_replace(
            'app.php',
            $extension . '/' . $filename,
            $_SERVER['PHP_SELF']
        ) ?: null;
    }

    /**
     * Function to check if on the current route name
     *
     * @param string $route
     * @return bool
     */
    public function isOnRoute($route)
    {
        if (isset($this->getRoutes()[$route])) {
            return $_SERVER['REQUEST_URI'] === $this->getRoutes()[$route];
        }

        return false;
    }

    /**
     * @param string $name
     * @param array $vars
     * @return mixed|null|string|string[]
     * @throws \Exception
     */
    public function path($name, $vars = [])
    {
        if (array_key_exists($name, $this->getRoutes())) {
            if (empty($vars)) {
                return $this->getRoutes()[$name];
            }

            /** @var array $routeData */
            $routeData = $this->getRouteCollection()[$name];

            /** @var array $routeVars */
            $routeVars = $routeData['vars'];

            foreach ($routeVars as $key => $routeVar) {
                $this->routeVars[$name][] = $routeVar;
            }

            $nbParams = $routeData['nbParams'] ?: 0;

            // Checking all parameters are given
            if (count($vars) < $nbParams) {
                throw new \InvalidArgumentException(
                    sprintf(
                        "Invalid number of parameters for route '%s'.",
                        $name
                    )
                );
            }

            // Checking parameter exists in route
            // (not used any way, only the order is important here... )
            foreach ($vars as $key => $val) {
                if (!in_array($key, $routeVars)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            "Invalid parameter '%s' for route '%s'.",
                            $key,
                            $name
                        )
                    );
                }
            }

            // Getting the route to fill (containing masks for parameters)
            /** @var string $route */
            $route = $routeData['url'];

            // For each parameter in the url
            for ($i = 0; $i < $nbParams; ++$i) {
                // Replacing the masks respecting the order
                // of given values in the array
                // limit 1, to not replace all at once
                $route = preg_replace(
                    "#\((.*?)\)#",
                    $vars[$routeVars[$i]],
                    $route,
                    1
                );
            }

            return $route;
        }

        return '#';
    }

    /**
     * @return array
     */
    public function getRoutes()
    {
        if (empty($this->routes)) {
            $this->routes = $GLOBALS['routes'];
        }

        return $this->routes;
    }

    /**
     * @return array|mixed
     */
    public function getRouteCollection()
    {
        if (empty($this->routeCollection)
            && isset($GLOBALS['routeCollection'])) {
            $this->routeCollection = $GLOBALS['routeCollection'];
        }

        return $this->routeCollection;
    }
}