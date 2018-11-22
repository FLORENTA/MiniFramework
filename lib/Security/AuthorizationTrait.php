<?php

namespace Lib\Security;

use Entity\User;
use Lib\Http\Request;

/**
 * Trait AuthorizationTrait
 * @package Lib\Security
 */
trait AuthorizationTrait
{
    /**
     * @param $role
     * @return bool
     */
    public function is_granted($role)
    {
        /** @var User|null $user */
        $user = $this->session->get('user');

        if (!is_null($user)) {
            return in_array($role, $user->getRoles());
        }

        return false;
    }
}