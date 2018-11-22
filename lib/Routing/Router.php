<?php

namespace Lib\Routing;

use Lib\Event\EventDispatcher;
use Lib\Http\Request;
use Lib\Security\Firewall;
use Lib\Security\SecurityException;
use Lib\Utils\Cache;
use Lib\Utils\CacheException;
use Lib\Utils\Logger;
use Lib\Utils\Message;

/**
 * Class Router
 * @package Lib\Routing
 */
class Router
{
    /** @var Logger $logger */
    private $logger;

    /** @var array */
    protected $routes;

    /** @var string $action */
    protected $action;

    /** @var array $matches */
    protected $matches;

    /** @var array $attributeList */
    protected $attributeList = [];

    /** @var Request $request */
    private $request;

    /** @var Cache $cache */
    private $cache;

    /** @var Firewall $firewall */
    private $firewall;

    /** @var EventDispatcher $eventDispatcher */
    private $eventDispatcher;

    /**
     * Router constructor.
     * @param Logger $logger
     * @param Firewall $firewall
     * @param Request $request
     * @param Cache $cache
     * @param EventDispatcher $eventDispatcher
     * @param $routingFile
     *
     */
    public function __construct(
        Logger $logger,
        Firewall $firewall,
        Request $request,
        Cache $cache,
        EventDispatcher $eventDispatcher,
        $routingFile
    )
    {
        $this->logger          = $logger;
        $this->request         = $request;
        $this->cache           = $cache;
        $this->routes          = \Spyc::YAMLLoad(ROOT_DIR . '/' . $routingFile);
        $this->firewall        = $firewall;
        $this->eventDispatcher = $eventDispatcher;
        $routeCollection       = [];
        $routeCacheFile        = ROOT_DIR . '/var/cache/routes.txt';

        try {
            /* Storing routes in file if not already exist */
            if (!$this->cache->fileAlreadyExistAndIsNotExpired($routeCacheFile)) {
                foreach ($this->routes as $name => $attributes) {
                    if (isset($attributes['url'])) {
                        $routeCollection[$name]['url'] = $attributes['url'];
                    }

                    if (isset($attributes['vars'])) {

                        if (!is_array($vars = $attributes['vars'])) {
                            $vars = [$vars];
                        }

                        $routeCollection[$name]['vars'] = $vars;
                        $routeCollection[$name]['nbParams'] = count($vars);
                    }
                }

                $this->cache->createFile($routeCollection);
            } else {
                $routeCollection = $this->cache->getFileContent();
            }
        } catch (CacheException $cacheException) {
            $this->logger->error($cacheException->getMessage());
        }

        // Contains all data about routes (url, vars...)
        $GLOBALS['routeCollection'] = $routeCollection;

        // Contains only routes url
        foreach ($routeCollection as $routeName => $routeData) {
            $GLOBALS['routes'][$routeName] = $routeData['url'];
        }
    }

    /**
     * @return string|false
     * @throws RouterException
     * @throws \Exception
     */
    public function getController()
    {
        try {
            /* Get the route matching the uri */
            /** @var array|false|null $matchingRoute */
            $matchingRoute = $this->getMatchingRoute();

            /* If no route found (null returned) */
            if (!isset($matchingRoute)) {
                throw new NoRouteFoundException(Message::NO_ROUTE_FOUND);
            }

            /* If false returned */
            if (!$matchingRoute) {
                return false;
            }

            /* Get corresponding controller and method to call */
            $controller = $matchingRoute['controller'];
            $this->action = $matchingRoute['action'];

            $attributes = [];

            /* Getting all vars linked to the route (url parameters) */
            if (isset($matchingRoute['vars'])) {
                if (!is_array($vars = $matchingRoute['vars'])) {
                    $attributes = [$vars];
                } else {
                    $attributes = $vars;
                }
            }

            /* Associating the matching value to the corresponding var */
            foreach ($this->matches as $key => $match) {
                /* The first key is the whole string (uri + parameters) */
                /* Then url parameters */
                if ($key > 0 && !empty($attributes)) {
                    $this->attributeList[$attributes[$key - 1]] = $match;
                }
            }

            $_GET = array_merge($_GET, $this->getAttributeList());
            $controller = $controller . 'Controller';
            $controllerInstance =  '\\Controller' . '\\' . $controller;

            /* Does the controller exist ? */
            if (!is_file(realpath(ROOT_DIR . '/src' . $controllerInstance . '.php'))) {
                throw new ControllerNotFoundException(
                    sprintf('The controller %s does not exist.', $controller)
                );
            }

            return $controllerInstance;

        } catch (SecurityException $securityException) {
            $this->logger->error($securityException->getMessage(), [
                '_class' => Router::class,
                '_Exception' => SecurityException::class
            ]);
            throw new RouterException();

        } catch (NoRouteFoundException $noRouteFoundException) {
            $this->logger->error($noRouteFoundException->getMessage(), [
                '_class' => Router::class,
                '_Exception' => NoRouteFoundException::class
            ]);
            throw new RouterException();

        } catch (ControllerNotFoundException $controllerNotFoundException) {
            $this->logger->error($controllerNotFoundException->getMessage(), [
                '_class' => Router::class,
                '_Exception' => ControllerNotFoundException::class
            ]);
            throw new RouterException();

        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), [
                '_class' => Router::class
            ]);
            throw $exception;
        }
    }

    /**
     * @return array|null|false
     * @throws SecurityException
     */
    public function getMatchingRoute()
    {
        $uri = $this->request->getRequestUri();

        foreach ($this->routes as $route) {
            if (preg_match('#^'.$route['url'].'$#', $uri, $matches)) {
                try {
                    if ($this->firewall->isRouteAuthorized($uri)) {
                        $this->matches = $matches;
                        return $route;
                    }
                } catch (SecurityException $securityException) {
                    throw $securityException;
                }

                /* If not authorized to access this route */
                return false;
            }
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function getMatches()
    {
        return $this->matches;
    }

    /**
     * @return array
     */
    public function getAttributeList()
    {
        return $this->attributeList;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }
}