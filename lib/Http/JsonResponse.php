<?php

namespace Lib\Model;

use Lib\Http\Response;

/**
 * Class JsonResponse
 * @package Lib\Model
 */
class JsonResponse
{
    /** @var string $content */
    private $content;

    /** @var string $statusCode */
    private $statusCode;

    /**
     * JsonResponse constructor.
     * @param string $content
     * @param string $statusCode
     * @param array $headers
     */
    public function __construct(
        $content = '',
        $statusCode = Response::SUCCESS,
        $headers = []
    )
    {
        $this->setHeader('Content-Type: application/json');
        $this->setHeader("HTTP/1.0 $statusCode");

        foreach ($headers as $header) {
            $this->setHeader($header);
        }

        $this->content    = $content;
        $this->statusCode = $statusCode;
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
        exit($this->content);
    }
}