<?php

namespace Lib\Exception;

use Lib\Http\Response;
use Lib\Model\JsonResponse;

/**
 * Class ResponseExceptionEvent
 * @package Lib\Exception\Response
 */
class ExceptionEvent
{
    /** @var \Exception */
    private $exception;

    /** @var Response $response */
    private $response;

    /**
     * ExceptionEvent constructor.
     * @param \Exception $exception
     */
    public function __construct($exception)
    {
        $this->exception = $exception;
    }

    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
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