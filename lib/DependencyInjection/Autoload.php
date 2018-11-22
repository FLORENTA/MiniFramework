<?php

namespace Lib\DependencyInjection;

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
            'Lib' => 'lib',
            'Lib\Controller' => 'lib/Controller',
            'Lib\DependencyInjection' => 'lib/DependencyInjection',
            'Lib\Event' => 'lib/Event',
            'Lib\Event\Events' => 'lib/Event/Events',
            'Lib\Exception' => 'lib/Exception',
            'Lib\Form' => 'lib/Form',
            'Lib\Http' => 'lib/Http',
            'Lib\Model' => 'lib/Model',
            'Lib\Model\Connection' => 'lib/Model/Connection',
            'Lib\Model\Entity' => 'lib/Model/Entity',
            'Lib\Model\Exception' => 'lib/Model/Exception',
            'Lib\Model\Manager' => 'lib/Model/Manager',
            'Lib\Model\Orm' => 'lib/Model/Orm',
            'Lib\Model\Relation' => 'lib/Model/Relation',
            'Lib\Process' => 'lib/Process',
            'Lib\Routing' => 'lib/Routing',
            'Lib\Security' => 'lib/Security',
            'Lib\Templating' => 'lib/Templating',
            'Lib\Utils' => 'lib/Utils'
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