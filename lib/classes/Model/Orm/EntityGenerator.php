<?php

namespace Classes\Model\Orm;

use Classes\Model\Relation\RelationType;
use Classes\Utils\ClassWriter;

/**
 * Class EntityGenerator
 * @package Classes\Model\Orm
 */
class EntityGenerator
{
    /** @var ClassMetaDataFactory $classMetaDataFactory */
    private $classMetaDataFactory;

    /** @var ClassWriter $classWriter */
    private $classWriter;

    /** @var string $mappingFileDirectory */
    private $mappingFileDirectory;

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
        $this->classWriter = $classWriter;
        $this->mappingFileDirectory = $mappingFileDirectory;
    }

    /**
     * @param string $entity
     */
    public function generateFile($entity)
    {
        $namespace = "Entity\\$entity";

        /** @var ClassMetaData $classMetaData */
        $classMetaData = $this->classMetaDataFactory->getClassMetaData($namespace);

        /** @var array $fields */
        $fields = $classMetaData->fields;

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

        $this->classWriter->initFile()
                          ->addNamespace()
                          ->addClass($entity);

        foreach ($fields as $field => $data) {
            $type = $classMetaData->getType($data);
            $type = $type === 'datetime' ? '\DateTime' : $type;

            $this->classWriter->addAttribute($field, $type);
        }

        foreach ($fields as $field => $data) {
            $type = $classMetaData->getType($data);
            $type = $type === 'datetime' ? '\DateTime' : $type;

            $this->classWriter->addSetter($field, $type)
                              ->addGetter($field, $type);
        }

        foreach ($oneToManyRelations as $relationField => $data) {
            $this->classWriter->addOneToManyRelation(
                $relationField,
                $data['target'],
                $data['mappedBy']
            );
        }

        foreach ($manyToOneRelations as $relationField => $data) {
            $this->classWriter->addManyToOneRelation(
                $relationField,
                $data['target'],
                $data['inversedBy']
            );
        }

        foreach ($manyToManyRelations as $relationField => $data) {

            /** @var bool $isOwningSide */
            $isOwningSide = $classMetaData->isOwningSide($data);

            $this->classWriter->addManyToManyRelation(
                $relationField,
                $data['target'],
                $isOwningSide ? null : $data['mappedBy'],
                $isOwningSide ? $data['inversedBy'] : null,
                $isOwningSide ? $data['joinTable'] : null
            );
        }

        $content = $this->classWriter->getContent();

        file_put_contents(
            $this->mappingFileDirectory . '/../' . $entity . '.php',
            $content
        );
    }

    public function generateFiles()
    {
        $loadedClassMetaData = $this->classMetaDataFactory->getLoadedClassMetaData();

        /** @var ClassMetaData $classMetaData */
        foreach ($loadedClassMetaData as $classMetaData) {
            $this->generateFile($classMetaData->name);
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