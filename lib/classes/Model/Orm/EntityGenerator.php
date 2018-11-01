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
        $entity = "Entity\\$entity";

        /** @var ClassMetaData $classMetaData */
        $classMetaData = $this->classMetaDataFactory->getClassMetaData($entity);

        /** @var array $fields */
        $fields = $classMetaData->fields;

        /** @var array $oneToMany */
        $oneToMany = $classMetaData->getRelations(
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

        foreach ($fields as $field => $data) {
            $this->classWriter->addSetter($field, $classMetaData->getType($data));
            $this->classWriter->addLineBreak();
        }

        $content = $this->classWriter->getContent();

        file_put_contents(
            $this->mappingFileDirectory . '/../test.php',
            $content
        );
    }
}