<?php

namespace Lib\Model\Orm;

use Lib\Model\Connection\PDOFactory;
use Lib\Utils\Cache;

/**
 * Class DatabaseMetaData
 * @package Lib\Model\Orm
 */
class DatabaseMetaData
{
    /** @var \PDO $pdo */
    private $pdo;

    /** @var Cache $cache */
    private $cache;

    /** @var array $tablesColumns */
    private $tablesColumns = [];

    /** @var array $table */
    private $tables = [];

    /** @var string $databaseName */
    private $databaseName;

    /**
     * DatabaseMetaData constructor.
     * @param PDOFactory $PDOFactory
     * @param Cache $cache
     * @param $database_name
     * @throws \Exception
     */
    public function __construct(
        PDOFactory $PDOFactory,
        Cache $cache,
        $database_name
    )
    {
        $this->pdo = $PDOFactory::getConnexion();
        $this->cache = $cache;
        $this->databaseName = $database_name;
        $this->storeTableColumns($database_name);
    }

    /**
     * @param string $dbname
     * @throws \Exception
     */
    private function storeTableColumns($dbname)
    {
        if (!$this->cache->fileAlreadyExistAndIsNotExpired(ROOT_DIR . '/var/cache/tables.txt') ) {

            $tables = $this->pdo->query("SHOW TABLES from " . $dbname);

            foreach ($tables as $table) {
                $this->tables[] = $table['Tables_in_' . $dbname];
            }

            foreach ($this->tables as $table) {
                $stmt = $this->pdo->prepare("SHOW COLUMNS FROM " . $dbname . ".$table");
                $stmt->execute();
                $columns = $stmt->fetchAll();

                foreach ($columns as $column) {
                    $this->tablesColumns[$table][] = $column['Field'];
                }
            }

            $this->cache->createFile($this->tablesColumns);

        } else {
            $this->tablesColumns = $this->cache->getFileContent();
        }
    }

    /**
     * @return array
     */
    public function getTablesColumns()
    {
        return $this->tablesColumns;
    }

    /**
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->databaseName;
    }
}