<?php

namespace Classes\DependencyInjection;

/**
 * Custom Autoload class
 */
class Autoload
{
    /**
     * Files without defined namespaces
     *
     * @var array
     */
    protected static $files = [];

    /** @var array */
    protected static $pathToNamespaces = [];

    /**
     * Autoload constructor.
     */
    public function __construct()
    {
        static::setNamespaces();
        static::setFiles();
        static::register();
    }

    /**
     * Function to register namespaces
     */
    public static function setNamespaces()
    {
        static::$pathToNamespaces = [
            'Controller' => 'src/Controller',
            'Entity' => 'src/Entity',
            'Service' => 'src/Service',
            'Form' => 'src/Form',
            'Model' => 'src/Model',
            'Classes' => 'lib/classes',
            'Classes\Controller' => 'lib/classes/Controller',
            'Classes\DependencyInjection' => 'lib/classes/DependencyInjection',
            'Classes\Event' => 'lib/classes/Event',
            'Classes\Event\Events' => 'lib/classes/Event/Events',
            'Classes\Form' => 'lib/classes/Form',
            'Classes\Http' => 'lib/classes/Http',
            'Classes\Model' => 'lib/classes/Model',
            'Classes\Model\Connection' => 'lib/classes/Model/Connection',
            'Classes\Model\Entity' => 'lib/classes/Model/Entity',
            'Classes\Model\Manager' => 'lib/classes/Model/Manager',
            'Classes\Model\Orm' => 'lib/classes/Model/Orm',
            'Classes\Model\Relation' => 'lib/classes/Model/Relation',
            'Classes\Process' => 'lib/classes/Process',
            'Classes\Routing' => 'lib/classes/Routing',
            'Classes\Security' => 'lib/classes/Security',
            'Classes\Utils' => 'lib/classes/Utils'
        ];
    }

    public static function setFiles()
    {
        static::$files = [
            'Spyc' => 'vendor/mustangostang/spyc/Spyc.php'
        ];
    }

    public static function register()
    {
        spl_autoload_register(function($className) {

            // E.g : $className = Parameters
            if (array_key_exists($className, static::$files)) {

                require ROOT_DIR . '/' . static::$files[$className];

            // E.g : $className = Form\UserForm
            } else {
                $array = explode('\\', $className);
                $class = array_pop($array);
                $ns = implode('\\', $array);

                $file = static::$pathToNamespaces[$ns] . '/' . $class . ".php";

                if (array_key_exists($ns, static::$pathToNamespaces)) {
                    require ROOT_DIR . '/' . $file;
                }
            }
        });
    }
}

// Instantiating the class to run autoload
new Autoload;