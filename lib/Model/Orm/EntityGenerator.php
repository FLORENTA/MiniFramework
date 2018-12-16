<?php

namespace Lib\Model\Orm;

use Lib\Model\Relation\RelationType;
use Lib\Utils\ClassWriter;

/**
 * Class EntityGenerator
 * @package Lib\Model\Orm
 */
class EntityGenerator
{
    /** @var ClassMetaDataFactory $classMetaDataFactory */
    private $classMetaDataFactory;

    /** @var ClassWriter $classWriter */
    private $classWriter;

    /** @var string $mappingFileDirectory */
    private $mappingFileDirectory;

    /** @var string $entityDirectory */
    private $entityDirectory;

    /**
     * EntityGenerator constructor.
     * @param ClassMetaDataFactory $classMetaDataFactory
     * @param ClassWriter $classWriter
     * @param string $mappingFileDirectory
     */
    public function __construct(
        ClassMetaDataFactory $classMetaDataFactory,
        ClassWriter $classWriter,
        $mappingFileDirectory
    )
    {
        $this->classMetaDataFactory = $classMetaDataFactory;
        $this->classWriter          = $classWriter;
        $this->mappingFileDirectory = ROOT_DIR . '/' . $mappingFileDirectory;
        $this->entityDirectory      = ROOT_DIR . '/src/Entity';

        if (!is_dir($this->entityDirectory)) {
            mkdir($this->entityDirectory);
        }
    }

    /**
     * @param string|ClassMetaData $arg
     */
    public function generateFile($arg)
    {
        $classMetaData = &$arg;

        if (!$classMetaData instanceof ClassMetaData) {
            $namespace = "Entity\\$arg";

            /** @var ClassMetaData $classMetaData */
            $classMetaData = $this->classMetaDataFactory->getClassMetaData($namespace);
        }

        /** @var array $fields */
        $fields = $classMetaData->fields;

        /** @var array $oneToOneRelations */
        $oneToOneRelations = $classMetaData->getRelations(
            RelationType::ONE_TO_ONE
        );

        /** @var array $oneToMany */
        $oneToManyRelations = $classMetaData->getRelations(
            RelationType::ONE_TO_MANY
        );

        /** @var array $manyToOneRelations */
        $manyToOneRelations = $classMetaData->getRelations(
            RelationType::MANY_TO_ONE
        );

        /** @var array $manyToManyRelations */
        $manyToManyRelations = $classMetaData->getRelations(
            RelationType::MANY_TO_MANY
        );

        /* Beginning file writing process */
        $this->classWriter->initFile()
                          ->addNamespace()
                          ->addClass($classMetaData->name);

        /* Creating attributes (not related to relations) */
        foreach ($fields as $field => $data) {
            $type = $classMetaData->getType($data);
            $type = $type === 'datetime' ? '\DateTime' : $type;

            $this->classWriter->addAttribute($field, $type);
        }

        /* Add setters and getters for each attribute above (not related to relations) */
        foreach ($fields as $field => $data) {
            $type = $classMetaData->getType($data);
            $type = $type === 'datetime' ? '\DateTime' : $type;

            $this->classWriter->addSetter($field, $type)
                              ->addGetter($field, $type);
        }

        foreach ($oneToOneRelations as $relationField => $data) {

            /** @var bool $isOwningSide */
            $isOwningSide = $classMetaData->isOwningSide($data);

            $joinColumn = null;

            if ($isOwningSide) {
                $joinColumn = $classMetaData->getJoinColumn($data);
            }

            $this->classWriter->addOneToOneRelation(
                $relationField,
                $data['target'],
                $isOwningSide ? null : $data['mappedBy'],
                $isOwningSide ? $data['inversedBy'] : null,
                $isOwningSide ? $joinColumn : null
            );
        }

        /* Add attributes and setters and getters of oneToMany relations */
        foreach ($oneToManyRelations as $relationField => $data) {
            $this->classWriter->addOneToManyRelation(
                $relationField,
                $data['target'],
                $data['mappedBy']
            );
        }

        /* Add attributes and setters and getters of manyToOne relations */
        foreach ($manyToOneRelations as $relationField => $data) {
            $this->classWriter->addManyToOneRelation(
                $relationField,
                $data['target'],
                $data['inversedBy']
            );
        }

        /* Add attributes and setters and getters of manyToMany relations */
        foreach ($manyToManyRelations as $relationField => $data) {

            /** @var bool $isOwningSide */
            $isOwningSide = $classMetaData->isOwningSide($data);
            $joinTable = null;

            if ($isOwningSide) {
                /** @var ClassMetaData $targetClassMetaData */
                $targetClassMetaData = $this->classMetaDataFactory->getClassMetaData($data['target']);
                // Is joinTable defined in the manyToOne side yaml file ?
                /** @var string $joinTable */
                $joinTable = $classMetaData->getJoinTable($data)
                    ?: $targetClassMetaData->table . '_' . $classMetaData->table;
            }

            $this->classWriter->addManyToManyRelation(
                $relationField,
                $data['target'],
                $isOwningSide ? null : $data['mappedBy'],
                $isOwningSide ? $data['inversedBy'] : null,
                $isOwningSide ? $joinTable : null
            );
        }

        /** @var string $content the file content generated */
        $content = $this->classWriter->getContent();

        file_put_contents(
            $this->entityDirectory . '/' . $classMetaData->name . '.php',
            $content
        );
    }

    /**
     * Function to generate all entity classes
     * @see "generate entities" command
     */
    public function generateFiles()
    {
        /** @var array $loadedClassMetaData */
        $loadedClassMetaData = $this->classMetaDataFactory->getLoadedClassMetaData();

        /** @var ClassMetaData $classMetaData */
        foreach ($loadedClassMetaData as $classMetaData) {
            $this->generateFile($classMetaData);
        }
    }

    /**
     * @return ClassMetaDataFactory
     */
    public function getClassMetaDataFactory()
    {
        return $this->classMetaDataFactory;
    }
}