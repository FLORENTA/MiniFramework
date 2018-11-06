<?php

namespace Lib\Event\Events;

/**
 * Class StatisticEvent
 * @package Lib\Event\Events
 */
class StatisticEvent
{
    /** @var string  */
    private $uri;

    /**
     * StatisticEvent constructor.
     * @param string $uri
     */
    public function __construct($uri)
    {
        $this->uri = $uri;
    }

    /** @return string */
    public function getUri()
    {
        return $this->uri;
    }
}