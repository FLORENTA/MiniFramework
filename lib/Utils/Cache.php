<?php

namespace Lib\Utils;

use Lib\Exception\Cache\CacheException;

/**
 * Class Cache
 * @package Lib
 */
class Cache
{
    /** @var int $cacheExpiration */
    private $cacheExpiration;

    /** @var string $content */
    private $content;

    /**
     * Cache constructor.
     * @param $cacheExpiration
     * @throws \Exception
     */
    public function __construct($cacheExpiration)
    {
        if (!is_numeric($cacheExpiration)) {
            throw new CacheException(
                'The parameter cacheExpiration must be a number or a numeric string.'
            );
        }

        $this->cacheExpiration = $cacheExpiration;
    }

    /** @var string $file */
    protected $file;

    /**
     * @param $file
     * @return bool
     * @throws CacheException
     */
    public function fileAlreadyExistAndIsNotExpired($file)
    {
        $this->file = $file;
        $fileExist = is_file($file);
        $fileIsNotExpired = false;

        if ($fileExist) {
            $expirationDate = $this->getExpirationDate();
            /* If substring not a figure */
            if(!is_numeric($expirationDate)) {
                $message = sprintf('Unknown expiration date for file %s', $this->file);
                throw new CacheException($message);
            }

            $fileIsNotExpired = time() < $expirationDate;
        }

        return $fileExist && $fileIsNotExpired;
    }

    /**
     * @param array $data
     */
    public function createFile($data)
    {
        // Empty the file
        file_put_contents($this->file, '');
        /** @var integer $expirationDate */
        $expirationDate = time() + $this->cacheExpiration;
        $fh = fopen($this->file, 'a+');
        fwrite($fh, serialize($data));
        fwrite($fh, $expirationDate); // Expiration at the end of the file
        fclose($fh);
    }

    public function getExpirationDate()
    {
        // To avoid file_get_contents two times (getFileContent called after)
        $this->content = file_get_contents($this->file);
        return substr($this->content, -10); // The last ten characters of the string
    }

    /**
     * @return array
     */
    public function getFileContent()
    {
        return unserialize($this->content);
    }
}