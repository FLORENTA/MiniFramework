<?php

namespace Lib\Templating;

use Lib\Http\Response;
use Lib\Http\Session;
use Lib\Throwable\Response\RenderException;

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
     * @param $template
     * @param array $parameters
     * @return Response
     * @throws RenderException
     */
    public function render($template, array $parameters = [])
    {
        $template = str_replace('.php', '', $template);

        /** @var Response $response */
        $response = new Response;

        $vars = [];

        foreach ($parameters as $key => $value) {
            $$key = $value;
            $vars[$key] = $value;
        }

        ob_start();

        if (!@include ROOT_DIR . '/src/Resources/views/templates/' . $template . '.php') {
            throw new RenderException(
                sprintf('The template %s does not exist.', $template)
            );
        }

        // used in layout.php
        $content = preg_replace_callback_array([
            '/{{\spath\(.*\)\s}}/' => function($matches) {
                $path = preg_split('/({{\s|\s}})/', $matches[0])[1];
                /* Removing spaces and useless characters */
                $path = str_replace(['path(', '{', '}', ')', '\'', '\\s'], '', $path);
                $pathData = explode(',', $path);
                $routeName = array_shift($pathData);
                $makeArrayFromString = function($array) {
                    $args = [];
                    array_walk($array, function($a) use (&$args) {
                        $ex = explode(':', $a);
                        $key = trim($ex[0]);
                        $value = trim($ex[1]);
                        $args[$key] = $value;
                    });
                    return $args;
                };
                $args = $makeArrayFromString($pathData);
                return call_user_func_array([$this, 'path'], [$routeName, $args]);
            },
            '/{{\s.*\s}}/' => function($matches) use ($vars) {
                $var = preg_split('/({{\s|\s}})/', $matches[0])[1];
                if (false === strpos($var, 'path')) {
                    return $vars[$var];
                }
            },
        ], ob_get_clean());

        ob_start();
        if (!@require_once ROOT_DIR . '/src/Resources/views/layout.php') {
            throw new RenderException('The layout template is not defined.');
        }
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
     * @throws RenderException
     */
    private function path($name, $vars = [])
    {
        if (array_key_exists($name, $this->getRoutes())) {
            if (empty($vars)) {
                return $this->getRoutes()[$name];
            }

            /** @var array $routeData */
            $routeData = $this->getRouteCollection()[$name];

            /** @var array $routeParts */
            $routeParts = array_filter(explode('/', $routeData['url']), function($part) {
                return !empty($part);
            });

            $routeVars = [];

            foreach ($routeParts as $key => $part) {
                if (false !== (strpos($routeParts[$key], '{'))) {
                    $varName = preg_split('#{|}#', $routeParts[$key])[1];
                    $routeVars[] =  $varName;
                }
            }

            $nbParams = count($routeVars);

            // Checking all parameters are given
            // All useless variable are simply ignored
            if (count($vars) < $nbParams) {
                throw new RenderException(
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
                    throw new RenderException(
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
                    "#\{$routeVars[$i]\}#",
                    $vars[$routeVars[$i]],
                    $route,
                    1
                );
            }

            return str_replace('/app.php', '', $_SERVER['SCRIPT_NAME']) . $route;
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