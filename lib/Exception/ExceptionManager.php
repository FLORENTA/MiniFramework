<?php

namespace Lib\Exception;

use Entity\User;
use Lib\Http\Session;
use Lib\Templating\Template;
use Lib\Utils\Logger;
use Lib\Utils\Message;

/**
 * Class ExceptionManager
 * @package Lib\Exception
 */
class ExceptionManager
{
    /** @var Template $templating */
    private $templating;

    /** @var Logger $logger */
    private $logger;

    /** @var Session $session */
    private $session;

    /**
     * ExceptionManager constructor.
     * @param Template $template
     * @param Logger $logger
     */
    public function __construct(
        Template $template,
        Logger $logger
    )
    {
        $this->templating = $template;
        $this->logger     = $logger;

        set_exception_handler([$this, 'exceptionHandler']);
    }

    /**
     * @param \Exception $exception
     */
    public function exceptionHandler($exception)
    {
        if (!empty($exception->getMessage())) {
            $this->logger->warning($exception->getMessage(), [
                '_Class' => ExceptionManager::class,
            ]);
        }

        $template = $this->templating->render('404', [
            'error' => Message::ERROR,
        ]);

        $template->send();
    }
}