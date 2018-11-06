<?php

namespace Lib\Http;

use Lib\Security\AuthorizationTrait;
use Lib\Utils\Logger;
use Lib\Utils\Message;

/**
 * Class Response
 * @package Lib
 */
class Response
{
    use AuthorizationTrait;

    /**
     * @var Session $session
     */
    protected $session;

    /**
     * @var array $routeCollection
     */
    protected $routeCollection = [];

    /**
     * @var array $routes
     */
    protected $routes = [];

    /**
     * @var array $routeVars
     */
    protected $routeVars = [];

    /**
     * @var Logger $logger
     */
    protected $logger;

    const SUCCESS = "200 OK";
    const CREATED = "201 Created";
    const UNAUTHORIZED = "401 Forbidden";
    const NOT_FOUND = "404 not found";
    const SERVER_ERROR = "500 Internal Server Error";

    /**
     * Response constructor.
     * @param Session $session
     * @param Logger $logger
     */
    public function __construct(Session $session, Logger $logger)
    {
        $this->session = $session;
        $this->logger = $logger;
    }

    /**
     * @param string $content
     */
    public function setHeader($content)
    {
        header($content);
    }

    /**
     * @return array|mixed
     */
    public function getRouteCollection()
    {
        if (empty($this->routeCollection) && isset($GLOBALS['routeCollection'])) {
            $this->routeCollection = $GLOBALS['routeCollection'];
        }

        return $this->routeCollection;
    }

    /**
     * @return array
     */
    public function getRoutes()
    {
        if (empty($this->routes)) {
            foreach ($this->getRouteCollection() as $route => $array) {
                $this->routes[$route] = $array['url'];
            }
        }

        return $this->routes;
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

        $trace = debug_backtrace()[0];
        $file = $trace['file'];
        $line = $trace['line'];

        $this->logger->error(
            sprintf('Unknown route "%s" called in "%s", at line "%s"', $route, $file, $line)
        );

        return false;
    }

    /**
     * getcwd() = web
     * @param string $filename
     * @return mixed
     */
    public function asset($filename)
    {
        $extension = explode('.', $filename)[1];

        return $file = '/' . $extension . '/' . $filename ?: null;
    }

    /**
     * @param string $name
     * @param array $vars
     * @return mixed|null|string|string[]
     * @throws \Exception
     */
    public function path($name, $vars = [])
    {
        try {
            if (array_key_exists($name, $this->getRoutes())) {
                if (empty($vars)) {
                    return $this->getRoutes()[$name];
                }

                // Gathering data about the route
                $routeData = $this->getRouteCollection()[$name];

                // Getting the route vars
                $routeVars = $routeData['vars'];

                foreach ($routeVars as $key => $routeVar) {
                    $this->routeVars[$name][] = $routeVar;
                }

                $nbParams = $routeData['nbParams'] ?: 0;

                // Checking all parameters are given
                if (count($vars) < $nbParams) {
                    throw new \InvalidArgumentException(
                        sprintf("Invalid number of parameters for route '%s'.", $name)
                    );
                }

                // Checking parameter exists in route (not used any way, only the order is important here... )
                foreach ($vars as $key => $val) {
                    if (!in_array($key, $routeVars)) {
                        throw new \InvalidArgumentException(
                            sprintf("Invalid parameter '%s' for route '%s'.", $key, $name)
                        );
                    }
                }

                // Getting the route to fill (containing masks for parameters)
                $route = $routeData['url'];

                // For each parameter in the url
                for ($i = 0; $i < $nbParams; ++$i) {
                    // Replacing the masks respecting the order of given values in the array (limit 1, to not replace all at once)
                    $route = preg_replace("#\((.*?)\)#", $vars[$routeVars[$i]], $route, 1);
                }

                return $route;
            }

            return '';

        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * @param string $content
     * @param string $status
     */
    public function send($content, $status = self::SUCCESS)
    {
        $this->setHeader("HTTP/1.0 $status");
        exit($content);
    }

    /**
     * @param string $content
     * @param string $status
     */
    public function sendJson($content, $status = self::SUCCESS)
    {
        $this->setHeader("HTTP/1.0 $status");
        $this->setHeader('Content-Type: application/json');
        exit(json_encode($content));
    }

    /**
     * @param string $template
     * @param array $parameters
     * @return false|string
     */
    public function render($template, array $parameters = [])
    {
        /* For footer in admin profile only */
        $delta = microtime(true) - $this->session->get('start');
        $processTimeFromRequestToRendering = number_format($delta, 3)*1000 . " ms";

        /* Used in templates */

        /** @var string $message */
        $message = $this->session->get('message');

        foreach ($parameters as $key => $value) {
            $$key = $value;
        }

        ob_start();
            require ROOT_DIR . '/src/Resources/views/templates/' . $template . '.php';
        // used in layout.php
        $content = ob_get_clean();

        ob_start();
            require ROOT_DIR . '/src/Resources/views/layout.php';
        return ob_get_flush();
    }

    /**
     * @param null $route
     * @return false|string
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
        if (array_key_exists($route, $this->getRoutes())) {
            $route = $this->getRoutes()[$route];
            $routeFound = true;
        }

        if (!$routeFound) {
            return $this->render('404', [
                'error' => Message::ERROR
            ]);
        }

        $host = $_SERVER['HTTP_HOST'];
        $scriptName = str_replace('/app.php', '', $_SERVER['SCRIPT_NAME']);

        $route = $host . $scriptName . $route;

        header("Location: http://$route");
        exit;
    }

    /**
     * Default called method if called method does not exist
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $trace = debug_backtrace()[0];
        $file = $trace['file'];
        $line = $trace['line'];
        $this->logger->error(
            sprintf('Unknown function "%s", called in "%s", at line "%s".', $name, $file, $line)
        );
    }
}