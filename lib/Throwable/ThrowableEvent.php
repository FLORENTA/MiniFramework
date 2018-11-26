<?php

namespace Lib\Throwable;

use Lib\Http\Response;
use Lib\Model\JsonResponse;

/**
 * Class ThrowableEvent
 * @package Lib\Throwable
 */
class ThrowableEvent
{
    /** @var \Throwable */
    private $throwable;

    /** @var Response $response */
    private $response;

    /**
     * ThrowableEvent constructor.
     * @param \Throwable $throwable
     */
    public function __construct($throwable)
    {
        $this->throwable = $throwable;
    }

    /**
     * @return \Throwable
     */
    public function getThrowable()
    {
        return $this->throwable;
    }

    /**
     * @param Response|JsonResponse $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @return Response|JsonResponse
     */
    public function getResponse()
    {
        return $this->response;
    }
}