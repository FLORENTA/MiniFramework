<?php

namespace Classes\Security;

trait AuthorizationTrait
{
    /**
     * @param $role
     * @return bool
     */
    public function is_granted($role)
    {
        if (!is_null($user = $this->session->get('user'))) {
            return in_array($role, $user->getRoles());
        }

        return false;
    }
}