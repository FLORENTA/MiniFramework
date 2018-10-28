<?php

namespace Classes\Utils;

/**
 * Class Logger
 * @package Classes
 */
class Logger
{
    const ERROR = 'Error';
    const CRITICAL = 'Critical';
    const DEBUG = 'Debug';
    const WARNING = 'Warning';
    const INFO = 'Info';

    protected $fh;

    /**
     * @param string $message
     */
    public function error($message)
    {
        $this->write($message, self::ERROR);
    }

    /**
     * @param string $message
     */
    public function warning($message)
    {
        $this->write($message, self::WARNING);
    }

    /**
     * @param string $message
     */
    public function debug($message)
    {
        $this->write($message, self::DEBUG);
    }

    /**
     * @param string $message
     */
    public function info($message)
    {
        $this->write($message, self::INFO);
    }

    /**
     * @param string $message
     */
    public function critical($message)
    {
        $this->write($message, self::CRITICAL);
    }

    /**
     * @param string $message
     * @param string $level
     */
    private function write($message, $level = self::INFO)
    {
        $this->fh = fopen(ROOT_DIR . '/var/logs/debug.txt', "a+");
        fwrite($this->fh, date("d/m/Y H:i:s", time()) . " : [" . $level . "] " . $message . "\r\n");
        fclose($this->fh);
    }
}