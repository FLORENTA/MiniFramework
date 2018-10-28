<?php

namespace Controller;

use Classes\Controller\Controller;

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