<?php

namespace Controller;

use Classes\Controller\Controller;
use Classes\Form\Form;
use Classes\Http\Request;
use Classes\Http\Session;
use Entity\Dummy;
use Form\DummyForm;

/**
 * Class DummyController
 * @package Controller
 */
class DummyController extends Controller
{
    /**
     * @param Request $request
     * @param Session $session
     * @return string
     */
    public function index(Request $request, Session $session)
    {
        /** @var Dummy $dummy */
        $dummy = new Dummy();

        /** @var Form $dummyForm */
        $dummyForm = $this->createForm(DummyForm::class, $dummy);

        if ($request->isMethod(Request::METHOD_POST)) {
            $dummyForm->handleRequest($request);
            $session->set('message', 'The form has been submitted.');
        }

        return $this->render('index', [
            'form' => $dummyForm->createView(),
            'dummy' => $dummy
        ]);
    }
}