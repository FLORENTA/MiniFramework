<?php

namespace Lib\DependencyInjection;

/**
 * Class DependencyInjection
 * @package Lib
 */
class DependencyInjection
{
    /** @var array $parameters */
    private $parameters= [];

    /** @var array $events */
    private $events = [];

    /**
     * DependencyInjection constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $pathToClasses = ROOT_DIR . '/lib/classes.yml';
        $pathToServices = ROOT_DIR . '/app/config/services.yml';

        $this->loadFileAndRegisterClasses($pathToClasses)
             ->loadFileAndRegisterClasses($pathToServices);
    }

    /**
     * @param $path
     * @return $this
     * @throws \Exception
     */
    private function loadFileAndRegisterClasses($path)
    {
        $fileContent = null;

        if (!file_exists($path)) {
            throw new \Exception(
                sprintf("Invalid file path %s", $path)
            );
        }

        $fileContent = \Spyc::YAMLLoad($path);

        $keys = array_keys($fileContent);
        $firstKey = end($keys);

        /* The considered key may be missing in the file */
        if (in_array($firstKey, ['classes', 'services'])) {
            $classes = $fileContent["$firstKey"];
            if (!empty($classes)) {
                $this->registerClass($classes);
            }
        }

        return $this;
    }

    /**
     * @param array $classes
     * @throws \Exception
     */
    private function registerClass($classes)
    {
        /* Building args */
        /* If arguments is an array, setting the array */
        /* If arguments is a string, putting it into an array */
        /* If arguments is not defined, setting an array with empty string */
        /* See container Line 48 */
        foreach ($classes as $id => $datas) {
            $this->parameters[$id]['class'] = $datas['class'];
            if (isset($datas['arguments'])) {
                if (is_array($datas['arguments'])) {
                    $this->parameters[$id]['arguments'] = $datas['arguments'];
                } else {
                    $this->parameters[$id]['arguments'] = [$datas['arguments']];
                }
            }

            if (isset($datas['events'])) {
                foreach ($datas['events'] as $event) {
                    if (isset($event['method']) && !empty($event['method'])) {
                        $this->events[$id]['events'][$event['name']] = $event['method'];
                    } else {
                        throw new \Exception(
                            sprintf("Undefined method for event %s.", $event['name'])
                        );
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }
}