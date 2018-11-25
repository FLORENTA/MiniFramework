<?php

namespace Lib\DependencyInjection;

use Lib\Utils\Tools;
use Spyc;

/**
 * Aims at building all classes and their dependencies when the application
 * is launched, based on classes.yml, services.yml, parameters.yml files
 * All instantiated classes are stored in an array with their alias as key to get
 * them later from the container
 *
 * Class ClassBuilder
 * @package Lib\DependencyInjection
 */
class ClassBuilder extends Container
{
    /** @var array $keysClasses */
    protected $keysClasses = [];

    /** @var array $classes */
    protected $classes = [];

    /** @var string $dir */
    protected $dir;

    /** @var array $classesWithConstructor */
    protected $classesWithConstructor = [];

    /** @var array $pendingInstances */
    protected $requiredInstances = [];

    /** @var array $pendingInstances */
    protected $pendingInstances = [];

    /**
     * ClassBuilder constructor.
     * @param array $classes
     * @throws \Exception
     */
    public function __construct(array $classes = [])
    {
        $this->classes = $classes;

        /** @var array $parametersFileContent */
        $parametersFileContent = Spyc::YAMLLoad(ROOT_DIR . '/app/config/parameters.yml');

        /** @var array $configFileContent */
        $configFileContent = Spyc::YAMLLoad(ROOT_DIR . '/app/config/config.yml');

        $this->parameters = array_merge(
            $parametersFileContent['parameters'],
            $configFileContent
        );

        if (!is_dir($dir = ROOT_DIR . '/var')) {
            mkdir($dir);
        }

        if (!is_dir($dir = ROOT_DIR . '/var/cache')) {
            mkdir($dir);
        }

        if (!is_dir($dir = ROOT_DIR . '/var/logs')) {
            mkdir($dir);
        }

        try {

            $this->dispatchClasses();

            array_walk($this->classesWithConstructor, [
                $this, 'instantiateClassesWithConstructor'
            ]);

            // Build recursively missing instances because lack of
            // arguments for their constructor

            while (count($this->requiredInstances) > 0) {
                array_walk($this->requiredInstances, [
                    $this, 'buildRequiredInstances'
                ]);
            }

        } catch (\Exception $e) {
            $this->get('logger')->warning($e->getMessage(), [
                '_Class' => ClassBuilder::class
            ]);
            throw $e;
        }
    }

    /**
     * Putting aside classes having a constructor
     *
     * @throws \Exception
     */
    public function dispatchClasses()
    {
        foreach ($this->classes as $alias => $classDefinition) {

            /** @var string $class */
            $class = $this->getClass($classDefinition, $alias);

            /* If the class has a constructor */
            if (!is_null((new \ReflectionClass($class))->getConstructor())) {
                $this->storeClassWithConstructor(
                    $alias,
                    $class,
                    $this->getArguments($classDefinition)
                );
            } else {
                /* No constructor, instantiating the class and storing it */
                $this->createInstance($class, [], $alias);
            }

            /* Storing the fully qualified path to class, by alias */
            $this->keysClasses[$alias] = $class;
        }
    }

    /**
     * Try to build classes having a constructor
     * If all the arguments of the class are strings (parameters) and / or
     * other classes without constructor, the class is instantiated with
     * the given arguments
     *
     * Else, the class is put aside with its instantiated arguments
     * The arguments requiring other ones will then be instantiated themselves
     * and merged to the existing ones to instantiate the class
     *
     * @param array $classWithConstructor
     * @param $alias
     * @throws \Exception
     */
    public function instantiateClassesWithConstructor($classWithConstructor, $alias)
    {
        $classArguments = $this->getArguments($classWithConstructor);

        $args = [];

        foreach ($classArguments as $classArgument) {

            /* The required class may have already been instantiated */
            if ($this->has($classArgument)) {
                $args[] = $this->get($classArgument);
                continue;
            }

            /**
             * &this will grow retroactively when the current context is updated
             * So that each class having a 'container' dependency will
             * have access to the same context as others
             */
            if ($classArgument === 'container') {
                $args[] = &$this;
                continue;
            }

            /* If parameter required */
            if (false !== strpos($classArgument, '%')) {
                $param = explode('%', $classArgument);

                if ($this->hasParameter($param[1])) {
                    $args[] = $this->getParameter($param[1]);
                }

                continue;
            }

            /* If the argument has a constructor and
             * potentially dependencies itself
             */
            if ($this->hasConstructor($classArgument)) {
                $this->storeRequiredInstance($classArgument, $alias);
            }
        }

        /** @var array $classDefinition */
        $classDefinition = $this->classesWithConstructor[$alias];

        /** @var string $class */
        $class = $this->getClass($classDefinition, $alias);

        if ($this->hasRequiredInstances($alias)) {
            /**
             * Storing the class with its already instantiated arguments
             *
             * These arguments will be merged with the other required ones
             * once the other ones are instantiated
             */
            $this->storeInstanceAsPending($class, $args, $alias);
        } else {
            /**
             * All arguments have been found, the class can be instantiated
             */
            $this->createInstance($class, $args, $alias);
        }
    }

    /**
     * Recursively instantiate remaining arguments
     * to enable some other classes instantiation
     *
     * E.g : if firewall needs request and session,
     * $key = firewall,
     * $requiredInstances = request and session
     *
     * @param array $requiredInstances
     * @param $key
     * @throws \Exception
     */
    public function buildRequiredInstances($requiredInstances, $key)
    {
        $args = [];

        /**
         * For the remaining required instances, some arguments may
         * have been instantiated in between
         */
        foreach ($requiredInstances as $requiredInstance) {
            if ($this->has($requiredInstance)) {
                $args[] = $this->get($requiredInstance);
            }
        }

        /* If all the arguments (required instances) of the instance
         * have already been instantiated, we can use them
         */
        if (count($args) === count($requiredInstances)) {

            /** @var string $class */
            $class = $this->getPendingInstanceClass($key);
            $reflectionClass = new \ReflectionClass($class);

            /**
             * Already instantiated/existing arguments linked to the class
             * are merged with the missing ones from now on instantiated,
             * enabling to instantiate the required class
             */
            $classArgs = array_merge($this->getPendingInstanceArguments($key), $args);

            /* If the class to instance has a constructor */
            if (!is_null($constructor = $reflectionClass->getConstructor())) {

                $constructorParameters = $constructor->getParameters();

                /**
                 * To get the order of the arguments to pass to the constructor
                 * @var array $constructorArgs
                 */
                $constructorArgs = [];

                foreach ($constructorParameters as $constructorParameter) {
                    /* If the parameter is an object */
                    if (!is_null($constructorParameterClass = $constructorParameter->getClass())) {
                        $classPath = $constructorParameterClass->getName();
                        $alias = $this->getAlias($classPath);
                        /* Store the alias of this constructor argument */
                        $constructorArgs[] = $alias;
                    } else {
                        /* Parameters (database_name...) */
                        $constructorArgs[] = $constructorParameter->getName();
                    }
                }

                /**
                 * The class arguments may be objects or string (parameters)
                 */
                foreach ($classArgs as $id => $classArg) {

                    /** @var string $alias */
                    $alias = $this->getAlias($classArg);
                    $classArgs[$alias] = $classArg;

                    /** Removing figure keys as alias add new keys to the array */
                    unset($classArgs[$id]);
                }

                $args = [];

                /* Re-ordering args to have the same order as the class constructor */
                foreach ($constructorArgs as $constructorArg) {
                    /* If the parameter declared in class constructor is camel cased */
                    /* Thus, not matching any of the parameters keys */
                    if (!isset($classArgs[$constructorArg])) {
                        if (is_string($constructorArg)) {
                            /** @var string $constructorArg */
                            $constructorArg = Tools::splitCamelCasedWords($constructorArg);
                            /** Checking that the transformed 'potential' parameter now exists */
                            try {
                                if ($this->hasParameter($constructorArg)) {
                                    $args[] = $this->getParameter($constructorArg);
                                }
                            } catch (\InvalidArgumentException $e) {
                                throw $e;
                            }
                        }
                    } else {
                        $args[] = &$classArgs[$constructorArg];
                    }
                }

                $this->createInstance($class, $args, $key);
            } else {
                $this->createInstance($class, [], $key);
            }

            $this->removeRequiredInstance($key);
            $this->removePendingInstance($key);
        }
    }

    /**
     * @param array $item
     * @return bool
     */
    private function isClassDefined($item)
    {
        return isset($item['class']);
    }

    /**
     * @param array $item
     * @param string $alias
     * @return mixed
     * @throws \Exception
     */
    private function getClass($item, $alias)
    {
        if ($this->isClassDefined($item)) {
            return $item['class'];
        }

        throw new \Exception(sprintf('Missing class definition for "%s"', $alias));
    }

    /**
     * @param array $item
     * @return mixed
     */
    private function getArguments($item)
    {
        return $this->hasArguments($item) ? $item['arguments'] : [];
    }

    /**
     * @param array $item
     * @return bool
     */
    private function hasArguments($item)
    {
        return isset($item['arguments']);
    }

    /**
     * Function to track classes having a constructor
     * with their (potential) arguments
     *
     * @param string $alias
     * @param string $class
     * @param null|array $arguments
     */
    private function storeClassWithConstructor($alias, $class, $arguments = [])
    {
        $this->classesWithConstructor[$alias]['class'] = $class;

        if (!empty($arguments)) {
            $this->classesWithConstructor[$alias]['arguments'] = $arguments;
        }
    }

    /**
     * Pending instances are instances having a constructor with arguments
     *
     * As the arguments may themselves require arguments to be instantiated,
     * putting aside the required class with its already instantiated arguments
     *
     * Merge of arguments will be done later when the missing arguments
     * and their dependencies are instantiated
     *
     * @param string $class
     * @param array $args
     * @param string $alias
     */
    private function storeInstanceAsPending($class, $args, $alias)
    {
        $this->pendingInstances[$alias]['class'] = $class;
        $this->pendingInstances[$alias]['arguments'] = $args;
    }

    /**
     * Store the required instances to instantiate later
     *
     * @param $classArgument
     * @param $alias
     */
    private function storeRequiredInstance($classArgument, $alias)
    {
        $this->requiredInstances[$alias][] = $classArgument;
    }

    /**
     * Storing the instantiated class
     *
     * @param object $instance
     * @param string $alias
     */
    private function storeInstance(&$instance, $alias)
    {
        $this->arrayOfInstances[$alias] = $instance;
    }

    /**
     * Does the class require arguments to be instantiated ?
     *
     * @param string $alias
     * @return bool
     */
    public function hasRequiredInstances($alias)
    {
        return isset($this->requiredInstances[$alias]);
    }

    /**
     * @param string $classArgument
     * @return bool
     */
    private function hasConstructor($classArgument)
    {
        return isset($this->classesWithConstructor[$classArgument]);
    }

    /**
     * Function to return a pending class by alias
     *
     * @param string $alias
     * @return mixed
     */
    private function getPendingInstanceClass($alias)
    {
        return $this->pendingInstances[$alias]['class'];
    }

    /**
     * @param string $alias
     * @return mixed
     */
    private function getPendingInstanceArguments($alias)
    {
        return $this->pendingInstances[$alias]['arguments'];
    }

    /**
     * Function to return the alias of the given class or parameter by its value
     *
     * @param string|object $arg
     * @return false|int|string
     * @throws \Exception
     */
    private function getAlias($arg)
    {
        if (is_object($arg)) {
            $arg = get_class($arg);
        }

        if (false !== ($serviceAlias = array_search($arg, $this->keysClasses))) {
            return $serviceAlias;
        } elseif (false !== ($parameterKey = array_search($arg, $this->parameters))) {
            return $parameterKey;
        } else {
            throw new \Exception(
                sprintf("Cannot find alias for argument %s", $arg)
            );
        }
    }

    /**
     * @param string $alias
     */
    private function removeRequiredInstance($alias)
    {
        unset($this->requiredInstances[$alias]);
    }

    /**
     * @param string $alias
     */
    private function removePendingInstance($alias)
    {
        $this->pendingInstances[$alias];
    }

    /**
     * @param \ReflectionClass|string $class
     * @param array $args the class arguments
     * @param string $alias
     * @return null
     * @throws \Exception
     */
    private function createInstance($class, $args, $alias)
    {
        $instance = null;

        // If string given
        if (!$class instanceof \ReflectionClass) {
            $class = new \ReflectionClass($class);
        }

        if (!empty($args)) {
            $instance = $class->newInstanceArgs($args);
        } else {
            $instance = $class->newInstance();
        }

        if (is_null($instance)) {
            throw new \Exception(
                sprintf('Cannot instantiate class "%s"', $class)
            );
        }

        $this->storeInstance($instance, $alias);
    }
}