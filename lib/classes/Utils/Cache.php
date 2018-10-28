<?php

namespace Classes\Utils;

/**
 * Class Cache
 * @package Classes
 */
class Cache
{
    /**
     * @var Logger $logger
     */
    protected $logger;

    /**
     * @var int $cacheExpiration
     */
    protected $cacheExpiration;

    /**
     * @var string $content
     */
    protected $content;

    /**
     * Cache constructor.
     * @param Logger $logger
     * @param $cacheExpiration
     * @throws \Exception
     */
    public function __construct(Logger $logger, $cacheExpiration)
    {
        $this->logger = $logger;

        if (!is_numeric($cacheExpiration)) {
            throw new \Exception(
                'The parameter cacheExpiration must be a number or a numeric string.'
            );
        }

        $this->cacheExpiration = $cacheExpiration;
    }

    /**
     * @var string $file
     */
    protected $file;

    /**
     * @param $file
     * @return bool
     * @throws \Exception
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
                throw new \Exception($message);
            } else {
                $fileIsNotExpired = time() < $expirationDate;
            }
        }

        return $fileExist && $fileIsNotExpired;
    }

    /**
     * @param array $data
     */
    public function createFile($data)
    {
        file_put_contents($this->file, ''); // To empty the file
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