<?php

namespace Lib\Http;

/**
 * Class Request
 * @package Lib
 */
class Request
{
    /**
     * @var Session $session
     */
    protected $session;

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_DELETE = 'DELETE';

    /**
     * Request constructor.
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * @param $arg
     * @return bool
     */
    public function isMethod($arg)
    {
        return $arg === $_SERVER['REQUEST_METHOD'];
    }

    /**
     * @return string
     */
    public function getRequestUri()
    {
        $uri = '';

        $requestUri = $_SERVER['REQUEST_URI'];
        $requestUriParts = explode('/', $requestUri);

        $scriptName = $_SERVER['SCRIPT_NAME'];
        $scriptNameParts = explode('/', $scriptName);

        $diffs = array_diff($requestUriParts, $scriptNameParts);

        /* Remove useless strings from the uri */
        foreach ($diffs as $diff) {
            $uri .= '/' . $diff;
        }

        return empty($uri) ? '/' : $uri;
    }

    /**
     * @return bool
     */
    public function isXMLHttpRequest()
    {
        if (array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER)) {
            return $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
        }

        return false;
    }

    /**
     * @return array
     */
    public function post()
    {
        return $_POST;
    }

    /**
     * @param string $key
     * @return null|string
     */
    public function get($key)
    {
        if(isset($_POST[$key]) && !empty($_POST[$key])) {
            return htmlspecialchars($_POST[$key]);
        } elseif (isset($_GET[$key]) && !empty($_GET[$key])) {
            return htmlspecialchars($_GET[$key]);
        } else {
            return null;
        }
    }

    /**
     * @return Session
     */
    public function getSession()
    {
        return $this->session;
    }
}