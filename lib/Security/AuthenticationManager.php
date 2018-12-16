<?php

namespace Lib\Security;

use Entity\User;
use Lib\Http\Session;
use Lib\Model\Orm\EntityManager;

/**
 * Class AuthenticationManager
 * @package Lib\Security
 */
class AuthenticationManager
{
    /** @var EntityManager $em */
    private $em;

    /** @var Session $session */
    private $session;

    /**
     * AuthenticationManager constructor.
     * @param EntityManager $entityManager
     * @param Session $session
     */
    public function __construct(EntityManager $entityManager, Session $session)
    {
        $this->em      = $entityManager;
        $this->session = $session;
    }

    /**
     * @param User $user
     * @return bool
     */
    public function authenticate(User $user)
    {
        $entity = $this->em->getEntityModel(User::class)->findOneBy([
            'username' => $user->getUsername()
        ]);

        if (empty($entity)) {
            $this->session->set('login_error', 'Utilisateur inconnu.');
            return false;
        }

        if (!password_verify($user->getPassword(), $entity->getPassword())) {
            $this->session->set('login_error', 'Mot de passe incorrect.');
            return false;
        }

        $this->session->set('user', $entity, true);
        return true;
    }
}