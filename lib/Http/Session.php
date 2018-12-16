<?php

namespace Lib\Http;

session_start();

/**
 * Class Session
 * @package Lib\Http
 */
class Session
{
    /**
     * @param string $name
     * @return null|mixed
     */
    public function get($name)
    {
        if ($this->has($name)) {
            $flash = $_SESSION['flashes'][$name]['value'];
            if (!$_SESSION['flashes'][$name]['persist']) {
                unset($_SESSION['flashes'][$name]);
            }
            return $flash;
        }

        return null;
    }

    /**
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        return isset($_SESSION['flashes'][$name]);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param bool $persist: should the data remain in session after 'getting' it once
     */
    public function set($name, $value, $persist = false)
    {
        $_SESSION['flashes'][$name]['value']   = $value;
        $_SESSION['flashes'][$name]['persist'] = $persist;
    }

    /**
     * @param string $name
     */
    public function remove($name)
    {
        unset($_SESSION['flashes'][$name]);
    }

    public function clear()
    {
        unset($_SESSION['flashes']);
    }
}