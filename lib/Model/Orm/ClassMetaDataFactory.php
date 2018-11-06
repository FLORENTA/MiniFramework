<?php

namespace Lib\Model\Orm;

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
    private $loadedClassMetaData = [];

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
     *
     * @throws \Exception
     */
    public function __construct($mappingFilesDirectory)
    {
        $this->mappingFilesDirectory = $mappingFilesDirectory;

        $this->doExtract();
    }

    /**
     * @throws \Exception
     */
    private function doExtract()
    {
        $mappingFilesDirectory = ROOT_DIR . '/' . $this->mappingFilesDirectory;
        $mappingFiles = scandir($mappingFilesDirectory);

        foreach ($mappingFiles as $mappingFile) {
            if (is_file($mappingFile = $mappingFilesDirectory . '/' . $mappingFile) &&
                is_readable($mappingFile)) {

                /** @var array $content */
                $this->currentFileContent = \Spyc::YAMLLoad($mappingFile);

                /** @var ClassMetaData $classMetaData */
                $this->classMetaData = $classMetaData = new ClassMetaData();

                /** the class path */
                $this->classPath = array_keys($this->currentFileContent)[0];

                /** @var array $data */
                $data = $this->currentFileContent[$this->classPath];

                /* Registering treated entities */
                $name = str_replace('Entity\\', '', $this->classPath);

                /** Tracking the registered classes */
                $this->mappedClasses[] = $name;

                $this->classMetaData->setName($name)->setClass($this->classPath);

                if (isset($data['model'])) {
                    $this->classMetaData->setModel(
                        $data['model']
                    );
                } else {
                    throw new \Exception(
                        sprintf('Missing model definition for class %s', $this->classPath)
                    );
                }

                if (isset($data['table'])) {
                    $this->classMetaData->setTable(
                        $data['table']
                    );
                } else {
                    throw new \Exception(
                        sprintf('Missing table name for class %s', $this->classPath)
                    );
                }

                if (isset($data['fields']) &&
                    !empty($data['fields'])) {
                    $this->classMetaData->setFields(
                        $data['fields']
                    );
                } else {
                    $this->classMetaData->setFields([]);
                }

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

                $this->loadedClassMetaData[$this->classPath] = $this->classMetaData;
            }
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

                $relation = $this->currentFileContent[$this->classPath][$relationType];

                $this->classMetaData->setRelations(
                    $relationType,
                    $relation
                );

                foreach ($relation as $r => $data) {
                    $this->relationalAttributes[$r] = $data['target'];
                }
            }
        }

        return $this;
    }

    /**
     * @param string $class
     * @return ClassMetaData
     */
    public function getClassMetaData($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (is_array($class)) {
            $class = get_class($class[0]);
        }

        return $this->loadedClassMetaData[$class];
    }

    /**
     * @return array
     */
    public function getLoadedClassMetaData()
    {
        return $this->loadedClassMetaData;
    }

    /**
     * @return array
     */
    public function getMappedClasses()
    {
        return $this->mappedClasses;
    }

    /**
     * @param string
     * @return array
     */
    public function getTargetEntityByProperty($property)
    {
        if (isset($this->relationalAttributes[$property])) {
            return $this->relationalAttributes[$property];
        };
    }
}