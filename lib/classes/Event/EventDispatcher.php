<?php

namespace Classes\Event;

use Classes\DependencyInjection\Container;

/**
 * Class EventDispatcher
 * @package Classes\Event
 */
class EventDispatcher
{
    /** @var Container $container */
    protected $container;

    /** @var array $eventListeners */
    protected $eventListeners = [];

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
        $registeredEventListeners = $this->getRegisteredEventListeners($eventName);

        foreach ($registeredEventListeners as &$registeredEventListener) {

            foreach ($registeredEventListener as $alias => $methodToExecute) {
                $eventListener = $this->container->get($alias);

                /* Call the method of the target Event listener and pass the Event argument */
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
     * @return mixed
     */
    public function getRegisteredEventListeners($eventName)
    {
        return $this->eventListeners[$eventName];
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