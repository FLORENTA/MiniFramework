<?php

namespace Lib\Routing;

use Lib\Event\EventDispatcher;
use Lib\Exception\Security\AccessDeniedException;
use Lib\Exception\Controller\ControllerNotFoundException;
use Lib\Exception\Routing\NoRouteFoundException;
use Lib\Exception\Routing\RoutingException;
use Lib\Http\Request;
use Lib\Security\Firewall;
use Lib\Utils\Cache;
use Lib\Exception\Cache\CacheException;
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

    /** @var string $requestUri */
    private $requestUri;

    /**
     * Router constructor.
     * @param Logger $logger
     * @param Firewall $firewall
     * @param Request $request
     * @param Cache $cache
     * @param EventDispatcher $eventDispatcher
     * @param $routingFile
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
        $this->requestUri      = $request->getRequestUri();
    }

    /**
     * @throws CacheException
     */
    public function setRoutes()
    {
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
            throw $cacheException;
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
     * @throws ControllerNotFoundException
     * @throws NoRouteFoundException
     * @throws AccessDeniedException
     * @throws \Exception
     */
    public function getController()
    {
        $controllerInstance = null;

        try {
            /** @var array|null $matchingRoute */
            $matchingRoute = $this->getMatchingRoute();

            if (!$this->firewall->isRouteAuthorized($this->requestUri)) {
                throw new AccessDeniedException(
                    sprintf("Access denied to route %s.", $this->requestUri)
                );
            }

            /* If no route found */
            if (is_null($matchingRoute)) {
                throw new NoRouteFoundException(Message::NO_ROUTE_FOUND);
            }

            /* Get route corresponding controller and method */
            $controller = $matchingRoute['controller'];
            $this->action = $matchingRoute['action'];

            $attributes = [];

            /* Getting all url parameters */
            if (isset($matchingRoute['vars'])) {
                if (!is_array($vars = $matchingRoute['vars'])) {
                    $attributes = [$vars];
                } else {
                    $attributes = $vars;
                }
            }

            /* Associating the matching value to the corresponding url parameter */
            foreach ($this->matches as $key => $match) {
                /* The first key is the whole string (uri + parameters) */
                /* Then url parameters */
                if ($key > 0 && !empty($attributes)) {
                    $this->attributeList[$attributes[$key - 1]] = $match;
                }
            }

            $_GET = array_merge($_GET, $this->getAttributeList());
            $controller = $controller . 'Controller';
            $controllerInstance = '\\Controller' . '\\' . $controller;

            /* Does the controller exist ? */
            if (!is_file(realpath(ROOT_DIR . '/src' . $controllerInstance . '.php'))) {
                throw new ControllerNotFoundException(
                    sprintf('The controller %s does not exist.', $controller)
                );
            }

            return $controllerInstance;

        } catch (AccessDeniedException $accessDeniedException) {
            $this->logger->warning($accessDeniedException->getMessage(), [
                '_Method' => __METHOD__,
                '_Exception' => AccessDeniedException::class,
                '_Uri' => $this->requestUri
            ]);
            throw $accessDeniedException;

        } catch (NoRouteFoundException $noRouteFoundException) {
            $this->logger->error($noRouteFoundException->getMessage(), [
                '_Method' => __METHOD__,
                '_Exception' => NoRouteFoundException::class,
                '_Uri' => $this->requestUri
            ]);
            throw $noRouteFoundException;

        } catch (ControllerNotFoundException $controllerNotFoundException) {
            $this->logger->critical($controllerNotFoundException->getMessage(), [
                '_Method' => __METHOD__,
                '_Exception' => ControllerNotFoundException::class,
            ]);
            throw $controllerNotFoundException;

        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), [
                '_Method' => __METHOD__,
            ]);
            throw $exception;
        }
    }

    /**
     * @return array|null
     */
    public function getMatchingRoute()
    {
        foreach ($this->routes as $route) {
            // Get the matching route
            if (preg_match(
                '#^'.$route['url'].'$#',
                $this->requestUri,
                $matches)) {

                $this->matches = $matches;
                return $route;
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