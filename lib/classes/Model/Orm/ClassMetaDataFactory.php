<?php

namespace Classes\Model\Orm;

use Classes\Model\Relation\RelationType;
use Classes\Utils\Tools;

/**
 * Class ClassMetaDataFactory
 * @package Classes\Model\Orm
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
                $content = \Spyc::YAMLLoad($mappingFile);

                /** @var ClassMetaData $classMetaData */
                $classMetaData = new ClassMetaData();

                /** @var string $id the class path */
                $id = array_keys($content)[0];

                /* Registering treated entities */
                $name = str_replace('Entity\\', '', $id);
                $this->mappedClasses[] = $name;

                $classMetaData->setName($name)->setClass($id);

                if (isset($content[$id]['model'])) {
                    $classMetaData->setModel($content[$id]['model']);
                } else {
                    throw new \Exception(
                        sprintf('Missing model definition for class %s', $id)
                    );
                }

                if (isset($content[$id]['table'])) {
                    $classMetaData->setTable($content[$id]['table']);
                } else {
                    throw new \Exception(
                        sprintf('Missing table name for class %s', $id)
                    );
                }

                if (isset($content[$id]['fields']) &&
                    !empty($content[$id]['fields'])) {
                    $classMetaData->setFields($content[$id]['fields']);
                } else {
                    $classMetaData->setFields([]);
                }

                $entityColumns = [];

                /**
                 * @var string $key
                 * @var array $field
                 */
                foreach ($classMetaData->fields as $key => $field) {
                    /* The column may be defined in the class related yaml file */
                    if (isset($field['columnName']) && !empty($field['columnName'])) {
                        $entityColumns[] = $field['columnName'];
                    } else {
                        /* Transforming the key into a columnName */
                        $entityColumns[] = Tools::splitCamelCasedWords($key);
                    }
                }

                $classMetaData->setColumns($entityColumns);

                if (isset($content[$id][RelationType::MANY_TO_ONE])) {
                    $classMetaData->setRelations(
                        RelationType::MANY_TO_ONE,
                        $content[$id][RelationType::MANY_TO_ONE]
                    );
                }

                if (isset($content[$id][RelationType::ONE_TO_MANY])) {

                    $relations = $content[$id][RelationType::ONE_TO_MANY];

                    $classMetaData->setRelations(
                        RelationType::ONE_TO_MANY,
                        $relations
                    );

                    foreach ($relations as $relation => $data) {
                        $this->relationalAttributes[$relation] = $data['target'];
                    }
                }

                if (isset($content[$id][RelationType::ONE_TO_ONE])) {

                    $relation = $content[$id][RelationType::ONE_TO_ONE];

                    $classMetaData->setRelations(
                        RelationType::ONE_TO_ONE,
                        $relation
                    );

                    foreach ($relation as $r => $data) {
                        $this->relationalAttributes[$r] = $data['target'];
                    }
                }

                if (isset($content[$id][RelationType::MANY_TO_MANY])) {
                    $classMetaData->setRelations(
                        RelationType::MANY_TO_MANY,
                        $content[$id][RelationType::MANY_TO_MANY]
                    );
                }

                $this->loadedClassMetaData[$id] = $classMetaData;
            }
        }
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