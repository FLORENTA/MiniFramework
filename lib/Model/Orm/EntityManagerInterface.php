<?php

namespace Lib\Model\Orm;

/**
 * Interface EntityManagerInterface
 * @package Model\Orm
 */
interface EntityManagerInterface
{
    /**
     * @param object $entity
     * @return mixed
     */
    public function persist($entity);

    /**
     * @param object|array $entity
     * @return mixed
     */
    public function remove($entity);

    /**
     * @param object $entity
     * @return mixed
     */
    public function update($entity);

    /**
     * @return \PDO
     */
    public function getConnection();

    /**
     * @param string|object|array $entity
     * @return array
     */
    public function getClassMetaData($entity);
}