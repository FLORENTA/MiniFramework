<?php

namespace Controller;

use Lib\Controller\Controller;

/**
 * Class LoginController
 * @package Controller
 */
class LoginController extends Controller
{
    /**
     * @return string
     */
    public function login()
    {
        return $this->render('login');
    }
}