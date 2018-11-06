<?php

namespace Lib\DependencyInjection;

/**
 * Class DependencyInjection
 * @package Lib
 */
class DependencyInjection
{
    /**
     * @var array $parameters
     */
    protected $parameters= [];

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

        if (file_exists($path)) {

            $fileContent = \Spyc::YAMLLoad($path);
        }

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

            if (isset($datas['event'])) {
                $this->parameters[$id]['event'] = $datas['event'];

                if (isset($datas['method']) && !empty($datas['method'])) {
                    $this->parameters[$id]['method'] = $datas['method'];
                } else {
                    throw new \Exception(
                        sprintf("Undefined method for event %s.", $datas['event'])
                    );
                }
            }
        }
    }

    /** @return array */
    public function getParameters()
    {
        return $this->parameters;
    }
}