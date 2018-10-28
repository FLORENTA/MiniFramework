<?php

namespace Classes\Event\Events;

/**
 * Class StatisticEvent
 * @package Classes\Event\Events
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