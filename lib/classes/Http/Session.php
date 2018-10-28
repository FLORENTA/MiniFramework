<?php

namespace Classes\Http;

session_start();

/**
 * Class Session
 * @package Classes\Http
 */
class Session
{
    /**
     * @param string $name
     * @return null|mixed
     */
    public function get($name)
    {
        if (isset($_SESSION['flashes'][$name])) {
            $flash = $_SESSION['flashes'][$name]['value'];
            if (!$_SESSION['flashes'][$name]['persist']) {
                unset($_SESSION['flashes'][$name]);
            }
            return $flash;
        }

        return null;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param bool $persist
     */
    public function set($name, $value, $persist = false)
    {
        $_SESSION['flashes'][$name]['value'] = $value;
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