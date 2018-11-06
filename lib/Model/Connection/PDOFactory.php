<?php

namespace Lib\Model\Connection;

/**
 * Class PDOFactory
 * @package Lib\Model\Connection
 */
class PDOFactory
{
    /** @var \PDO */
    private static $pdo;

    /** @var string $host */
    private static $host;

    /** @var string $database_name */
    private static $database_name;

    /** @var string $user */
    private static $user;

    /** @var string $password */
    private static $password;

    /** @var string $dsn */
    private static $dsn;

    /**
     * PDOFactory constructor.
     *
     * @param $host
     * @param $database_name
     * @param $user
     * @param $password
     */
    public function __construct($host, $database_name, $user, $password)
    {
        if (empty(static::$host) &&
            empty(static::$database_name) &&
            empty(static::$user) &&
            empty(static::$password)
        ) {
            static::$dsn = 'mysql:host='.$host.';dbname='.$database_name;
            static::$user = $user;
            static::$password = $password;

            self::connect();
        }
    }

    private static function connect()
    {
        static::$pdo = new \PDO(
            static::$dsn,
            static::$user,
            static::$password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * @return \PDO
     */
    public static function getConnexion()
    {
        return static::$pdo;
    }
}