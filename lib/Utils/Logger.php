<?php

namespace Lib\Utils;

/**
 * Class Logger
 * @package Lib
 */
class Logger
{
    const ERROR = 'Error';
    const CRITICAL = 'Critical';
    const DEBUG = 'Debug';
    const WARNING = 'Warning';
    const INFO = 'Info';

    /** @var string $fh */
    protected $fh;

    /**
     * @param string $message
     * @param array $context
     */
    public function error($message, $context = [])
    {
        $this->write($message, self::ERROR, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function warning($message, $context = [])
    {
        $this->write($message, self::WARNING, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function debug($message, $context = [])
    {
        $this->write($message, self::DEBUG, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function info($message, $context = [])
    {
        $this->write($message, self::INFO, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function critical($message, $context = [])
    {
        $this->write($message, self::CRITICAL, $context);
    }

    /**
     * @param string $message
     * @param string $level
     * @param array $context
     */
    private function write($message, $level = self::INFO, $context = [])
    {
        $c = ' [ ';
        $this->fh = fopen(ROOT_DIR . '/var/logs/debug.txt', "a+");
        foreach ($context as $key => $value) {
            $c .= $key . ' : ' . $value . ', ';
        }
        $c = rtrim($c, ', ');
        $c .= ' ] ';
        fwrite($this->fh, date("d/m/Y H:i:s", time()) . " : [" . $level . "] " . $message . ' ' . $c . "\r\n");
        fclose($this->fh);
    }
}