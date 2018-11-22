<?php

namespace Lib\Event;

use Lib\DependencyInjection\Container;

/**
 * Class EventDispatcher
 * @package Lib\Event
 */
class EventDispatcher
{
    /** @var Container $container */
    private $container;

    /** @var array $eventListeners */
    private $eventListeners = [];

    /**
     * EventDispatcher constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Notify the registered event listeners
     *
     * @param string $eventName
     * @param null $event
     */
    public function dispatch($eventName, $event = null)
    {
        /** @var array $registeredEventListeners */
        $registeredEventListeners = $this->getRegisteredEventListeners($eventName);

        foreach ($registeredEventListeners as &$registeredEventListener) {

            foreach ($registeredEventListener as $alias => $methodToExecute) {
                /** @var object|null $eventListener */
                $eventListener = $this->container->get($alias);

                /* Call the method of the target Event listener
                 * and pass the Event argument
                 */
                $eventListener->$methodToExecute($event);
            }
        }
    }

    /**
     * Add an eventListener for this event
     *
     * @param string $eventName
     * @param string $eventListener
     * @param string $methodToExecute
     */
    public function addEventListener($eventName, $eventListener, $methodToExecute)
    {
        $this->eventListeners[$eventName][] = [$eventListener => $methodToExecute];
    }

    /**
     * Returns the list of registered event listeners for this event
     *
     * @param $eventName
     * @return array
     */
    public function getRegisteredEventListeners($eventName)
    {
        if ($this->hasEventListener($eventName)) {
            return $this->eventListeners[$eventName];
        }

        return [];
    }

    /**
     * @param string $eventName
     * @return bool
     */
    public function hasEventListener($eventName)
    {
        return isset($this->eventListeners[$eventName]);
    }

    /**
     * For each class that has an event, register the event and the class (listener)
     *
     * @param array $parameters
     */
    public function registerEventListeners($parameters = [])
    {
        foreach ($parameters as $alias => &$classDefinition) {
            if (isset($classDefinition['event'])) {
                $this->addEventListener(
                    $classDefinition['event'],
                    $alias,
                    $classDefinition['method']
                );
            }
        }
    }
}