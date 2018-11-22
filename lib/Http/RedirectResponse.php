<?php

namespace Lib\Http;

use Lib\Routing\NoRouteFoundException;
use Lib\Utils\Message;

/**
 * Class RedirectResponse
 * @package Lib\Http
 */
class RedirectResponse
{
    /**
     * RedirectResponse constructor.
     * @param string $route
     * @throws NoRouteFoundException
     */
    public function __construct($route)
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
            throw new NoRouteFoundException(Message::NO_ROUTE_FOUND);
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
}