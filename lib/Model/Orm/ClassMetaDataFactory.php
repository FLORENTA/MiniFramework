<?php

namespace Lib\Model\Orm;

use Lib\Throwable\Model\ClassMetaDataException;
use Lib\Model\Relation\RelationType;
use Lib\Utils\Tools;

/**
 * Class ClassMetaDataFactory
 * @package Lib\Model\Orm
 */
class ClassMetaDataFactory
{
    /** @var string $mappingFilesDirectory */
    private $mappingFilesDirectory;

    /** @var array $loadedClassMetaData */
    private static $loadedClassMetaData = [];

    /** @var array $mappedClasses */
    private $mappedClasses = [];

    /** @var array $relationalAttributes */
    private $relationalAttributes = [];

    /** @var ClassMetaData $classMetaData */
    private $classMetaData;

    /** @var array $currentFileContent */
    private $currentFileContent;

    /** @var integer $classPath */
    private $classPath;

    /**
     * ClassMetaData constructor.
     * @param $mappingFilesDirectory
     * @throws ClassMetaDataException
     */
    public function __construct($mappingFilesDirectory)
    {
        $this->mappingFilesDirectory = $mappingFilesDirectory;

        try {
            $this->doExtract();
        } catch (ClassMetaDataException $classMetaDataException) {
            throw $classMetaDataException;
        }
    }

    /**
     * @throws ClassMetaDataException
     */
    private function doExtract()
    {
        $this->mappingFilesDirectory = ROOT_DIR . '/' . $this->mappingFilesDirectory;
        $mappingFiles = scandir($this->mappingFilesDirectory);

        try {
            array_walk($mappingFiles, [$this, 'createClassMetaData']);
        } catch (ClassMetaDataException $classMetaDataException) {
            throw $classMetaDataException;
        }
    }

    /**
     * @param string $mappingFile
     *
     * @throws ClassMetaDataException
     */
    private function createClassMetaData($mappingFile)
    {
        if (is_file($mappingFile = $this->mappingFilesDirectory . '/' . $mappingFile)
            && is_readable($mappingFile)) {

            /** @var array $content */
            $this->currentFileContent = \Spyc::YAMLLoad($mappingFile);

            if (empty($this->currentFileContent)) {
                throw new ClassMetaDataException(
                    sprintf('The file %s does not contain entity description.', $mappingFile)
                );
            }

            /** @var ClassMetaData $classMetaData */
            $this->classMetaData = new ClassMetaData();

            /** the class path */
            $this->classPath = array_keys($this->currentFileContent)[0];

            /** @var array $data */
            $data = $this->currentFileContent[$this->classPath];

            if (!isset($data['table'])) {
                throw new ClassMetaDataException(
                    sprintf('Missing table name for class %s', $this->classPath)
                );
            }

            if (!isset($data['model'])) {
                throw new ClassMetaDataException(
                    sprintf('Missing model definition for class %s', $this->classPath)
                );
            }

            /* Registering treated entities */
            $name = str_replace('Entity\\', '', $this->classPath);

            /** Tracking the registered classes */
            $this->mappedClasses[] = $name;

            $this->classMetaData
                ->setName($name)
                ->setClass($this->classPath)
                ->setModel($data['model'])
                ->setTable($data['table']);

            if (isset($data['fields']) &&
                !empty($data['fields'])) {
                $this->classMetaData->setFields(
                    $data['fields']
                );
            } else {
                $this->classMetaData->setFields([]);
            }

            // Fill in with defined column [forced camel-case] or
            // default column
            $entityColumns = [];

            /**
             * @var string $key
             * @var array $field
             */
            foreach ($this->classMetaData->fields as $key => $field) {
                /* The column may be defined in the class related yaml file */
                if (isset($field['columnName']) && !empty($field['columnName'])) {
                    $entityColumns[] = $field['columnName'];
                } else {
                    /* Transforming the attribute into a columnName */
                    $entityColumns[] = Tools::splitCamelCasedWords($key);
                }
            }

            $this->classMetaData->setColumns($entityColumns);

            $this->addRelations();

            self::$loadedClassMetaData[$this->classPath] = $this->classMetaData;
        }
    }

    /**
     * @return $this
     */
    private function addRelations()
    {
        /** @var array $relationTypes */
        $relationTypes = RelationType::getRelationTypes();

        foreach ($relationTypes as $relationType) {
            if (isset($this->currentFileContent[$this->classPath][$relationType])) {

                // E.g : the oneToOne key [$relationType] contains for example
                // 2 attributes. The 2 attributes and their related data
                // will be in $relation
                /** @var array $relation */
                $relation = $this->currentFileContent[$this->classPath][$relationType];

                $this->classMetaData->setRelations(
                    $relationType,
                    $relation
                );

                // Storing the target entity path of a specific relational attribute
                foreach ($relation as $r => $data) {
                    $this->relationalAttributes[$r] = $data['target'];
                }
            }
        }

        return $this;
    }

    /**
     * Returns a loaded class meta data
     *
     * @param string $class
     * @return ClassMetaData
     */
    public static function getClassMetaData($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (is_array($class)) {
            $class = get_class($class[0]);
        }

        return self::$loadedClassMetaData[$class];
    }

    /**
     * Returns all the loaded class meta data
     *
     * @return array
     */
    public static function getLoadedClassMetaData()
    {
        return self::$loadedClassMetaData;
    }

    /**
     * Classes defined in src/Resources/orm
     *
     * @return array
     */
    public function getMappedClasses()
    {
        return $this->mappedClasses;
    }

    /**
     * @param string
     * @return array|null
     */
    public function getTargetEntityByProperty($property)
    {
        if (isset($this->relationalAttributes[$property])) {
            return $this->relationalAttributes[$property];
        };

        return null;
    }
}