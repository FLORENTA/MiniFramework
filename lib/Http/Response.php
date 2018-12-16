<?php

namespace Lib\Http;

use Lib\Security\AuthorizationTrait;

/**
 * Class Response
 * @package Lib
 */
class Response
{
    use AuthorizationTrait;

    const SUCCESS = "200 OK";
    const CREATED = "201 Created";
    const UNAUTHORIZED = "401 Forbidden";
    const NOT_FOUND = "404 not found";
    const SERVER_ERROR = "500 Internal Server Error";

    /** @var string $content */
    private $content;

    /** @var string $statusCode */
    private $statusCode;

    /**
     * Response constructor.
     * @param string $content
     * @param string $statusCode
     */
    public function __construct(
        $content = '',
        $statusCode = self::SUCCESS
    )
    {
        $this->setHeader("HTTP/1.0 $statusCode");
        $this->setContent($content);
    }

    /**
     * @param string $content
     *
     * @return $this;
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @param string $statusCode
     *
     * @return $this;
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * @param string $content
     */
    public function setHeader($content)
    {
        header($content);
    }

    public function send()
    {
        echo $this->content;
        exit;
    }

    /**
     * Default called method if called method does not exist
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
//        $trace = debug_backtrace()[0];
//        $file = $trace['file'];
//        $line = $trace['line'];
//        $this->logger->error(
//            sprintf('Unknown function "%s", called in "%s", at line "%s".', $name, $file, $line)
//        );
    }
}