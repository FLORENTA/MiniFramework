<?php

namespace Lib\Http;

use Lib\Throwable\Routing\NoRouteFoundException;
use Lib\Utils\Message;

/**
 * Class RedirectResponse
 * @package Lib\Http
 */
class RedirectResponse
{
    /** @var string $route */
    private $route;

    /**
     * RedirectResponse constructor.
     * @param $route
     */
    public function __construct($route)
    {
        $this->route = $route;
    }

    /**
     * @throws NoRouteFoundException
     */
    public function send()
    {
        if (is_null($this->route)) {
            header('location:' . $_SERVER['HTTP_REFERER']);
            exit; // exit otherwise, the session key will be unset before using it !!
        }

        /* In case of problem during route collection build */
        /* Not possible to match the $route value against a set of routes as do not exist */
        $routeFound = $GLOBALS['routes'][$this->route] ?? false;

        if (!$routeFound) {
            throw new NoRouteFoundException(Message::NO_ROUTE_FOUND);
        }

        $host = $_SERVER['HTTP_HOST'];

        $scriptName = str_replace('/app.php', '', $_SERVER['SCRIPT_NAME']);

        $route = $host . $scriptName . $routeFound;

        header("Location: http://$route");
        exit;
    }
}