<?php

namespace Lib\Throwable;

use Lib\Http\Request;
use Lib\Http\Response;
use Lib\Model\JsonResponse;
use Lib\Templating\Template;
use Lib\Utils\Logger;
use Lib\Utils\Message;

/**
 * Class ThrowableListener
 * @package Lib\Throwable
 */
class ThrowableListener
{
    /** @var Request $request */
    private $request;

    /** @var Template $templating */
    private $templating;

    /** @var Logger $logger */
    private $logger;

    /**
     * ThrowableListener constructor.
     */
    public function __construct()
    {
        $this->request    = new Request;
        $this->templating = new Template;
        $this->logger     = new Logger;
    }

    /**
     * @param ThrowableEvent $throwable
     * @return ThrowableEvent
     */
    public function sendResponseForThrowableEvent($throwable)
    {
        if ($this->request->isXMLHttpRequest()) {
            $jsonResponse = new JsonResponse(
                Message::ERROR,
                Response::SERVER_ERROR
            );

            $throwable->setResponse($jsonResponse);
        }

        /** @var Response $response */
        $response = $this->templating->render('404', [
            'error' => $throwable->getThrowable()->getMessage()
        ]);

        $throwable->setResponse($response);

        return $throwable;
    }
}