<?php

namespace Lib\Routing;

use Lib\Event\EventDispatcher;
use Lib\Throwable\Security\AccessDeniedException;
use Lib\Throwable\Controller\ControllerNotFoundException;
use Lib\Throwable\Routing\NoRouteFoundException;
use Lib\Http\Request;
use Lib\Security\Firewall;
use Lib\Utils\Cache;
use Lib\Throwable\Cache\CacheException;
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

    /** @var array $vars */
    private $vars = [];

    /** @var array $paramOrder */
    private $paramOrder = [];

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

            $_GET = array_merge($_GET, $this->getVars());
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
            /** @var array $routeParts */
            $routeParts = array_filter(explode('/', $route['url']), function($part) {
                return !empty($part);
            });

            /** @var array $uriParts */
            $uriParts = array_filter(explode('/', $this->requestUri), function($part) {
                return !empty($part);
            });

            // If same length
            if (count($uriParts) === count($routeParts)) {
                $match = true;

                foreach ($uriParts as $key => $part) {
                    if ($routeParts[$key] !== $part) {
                        if (false !== (strpos($routeParts[$key], '{'))) {
                            $varName = preg_split('#{|}#', $routeParts[$key])[1];
                            $this->vars[$varName][] =  $part;
                            $this->paramOrder[] = $varName;
                        } else {
                            $match = false;
                        }
                    }
                }

                if ($match) {
                    return $route;
                }
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
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $route
     *
     * @return string
     */
    public function createUrl($route = '')
    {
        $path = $this->routes[$route]['url'];

        $scriptName = str_replace('/app.php', '', $_SERVER['SCRIPT_NAME']);

        return $scriptName . $path;
    }

    /**
     * @return array
     */
    public function getVars()
    {
        return $this->vars;
    }

    /**
     * @return array
     */
    public function getParamOrder()
    {
        return $this->paramOrder;
    }
}