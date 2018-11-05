<?php

namespace Controller;

use Classes\Controller\Controller;
use Classes\Form\Form;
use Classes\Http\Request;
use Classes\Http\Session;
use Classes\Model\Orm\EntityManager;
use Entity\Dummy;
use Entity\Image;
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
     * @param EntityManager $entityManager
     * @return string
     * @throws \Exception
     */
    public function index(
        Request $request,
        Session $session,
        EntityManager $entityManager
    )
    {
        /** @var Dummy $dummy */
        $dummy = new Dummy();

        /** @var Image $image */
        $image = new Image();

        $dummy->setImage($image);
        $image->setDummy($dummy);

        /** @var Form $dummyForm */
        $dummyForm = $this->createForm(DummyForm::class, $dummy);

        if ($request->isMethod(Request::METHOD_POST)) {
            $dummyForm->handleRequest($request);
            $entityManager->persist($dummy);
            $session->set('message', 'The form has been submitted.');
        }

        return $this->render('index', [
            'form' => $dummyForm->createView(),
            'dummy' => $dummy,
            'image' => $image
        ]);
    }
}