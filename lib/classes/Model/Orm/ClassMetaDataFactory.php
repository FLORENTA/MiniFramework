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

                $classMetaData->setClass($id);

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

                if (isset($content[$id]['fields'])) {
                    $classMetaData->setFields($content[$id]['fields']);
                } else {
                    throw new \Exception(
                        sprintf('Missing fields definition for class %s', $id)
                    );
                }

                $entityColumns = [];

                /**
                 * @var string $key
                 * @var array $field
                 */
                foreach ($classMetaData->fields as $key => $field) {
                    /* The column may be defined in the class related yaml file */
                    if (isset($field['columnName'])) {
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
                    $classMetaData->setRelations(
                        RelationType::ONE_TO_MANY,
                        $content[$id][RelationType::ONE_TO_MANY]
                    );
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
}