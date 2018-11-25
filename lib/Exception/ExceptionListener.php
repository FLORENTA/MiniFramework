<?php

namespace Lib\Exception;

use Lib\Http\Request;
use Lib\Http\Response;
use Lib\Model\JsonResponse;
use Lib\Templating\Template;
use Lib\Utils\Logger;
use Lib\Utils\Message;

/**
 * Class ExceptionListener
 * @package Lib\Exception
 */
class ExceptionListener
{
    /** @var Request $request */
    private $request;

    /** @var Template $templating */
    private $templating;

    /** @var Logger $logger */
    private $logger;

    /**
     * ExceptionListener constructor.
     */
    public function __construct()
    {
        $this->request    = new Request;
        $this->templating = new Template;
        $this->logger     = new Logger;
    }

    /**
     * @param ExceptionEvent $event
     * @return ExceptionEvent
     */
    public function sendResponseForException($event)
    {
        if ($this->request->isXMLHttpRequest()) {
            $jsonResponse = new JsonResponse(
                Message::ERROR,
                Response::SERVER_ERROR
            );

            $event->setResponse($jsonResponse);
        }

        /** @var Response $response */
        $response = $this->templating->render('404', [
            'error' => $event->getException()->getMessage()
        ]);

        $event->setResponse($response);

        return $event;
    }
}