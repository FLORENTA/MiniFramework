<?php

namespace Controller;

use Entity\User;
use Form\UserForm;
use Lib\Controller\Controller;
use Lib\Http\RedirectResponse;
use Lib\Http\Request;
use Lib\Http\Response;
use Lib\Security\AuthenticationManager;

/**
 * Class LoginController
 * @package Controller
 */
class LoginController extends Controller
{
    /**
     * @param Request $request
     * @param AuthenticationManager $authenticationManager
     * @return RedirectResponse|Response
     */
    public function login(Request $request, AuthenticationManager $authenticationManager)
    {
        $user = new User;
        $loginForm = $this->createForm(UserForm::class, $user);
        $loginForm->handleRequest($request);

        if ($request->isMethod(Request::METHOD_POST)) {
            if (!$authenticationManager->authenticate($user)) {
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('login', [
            'loginForm' => $loginForm->createView(),
            'error' => $request->getSession()->get('login_error')
        ]);
    }
}