<?php

namespace Lib\Security;

use Lib\Utils\Message;

/**
 * Class Firewall
 * @package Lib\Security
 */
class Firewall extends Security
{
    /**
     * @param $uri
     * @return bool
     * @throws \Exception
     */
    public function isRouteAuthorized($uri)
    {
        try {
            $this->storeRolesAndRelatedPaths();
        } catch (\Exception $exception) {
            throw $exception;
        }

        if ($this->isFirewallActivated) {

            $role = null;

            foreach ($this->roles_paths as $key => $role_paths) {
                /** @var string $str */
                $str = implode('|', $role_paths);
                /* Getting the role(s) for this url */
                if (preg_match("#$str#", $uri, $matches)) {
                    $role = &$key;
                    break;
                }
            }

            if ((!empty($role) &&
                    $role !== Role::ROLE_ANONYMOUS &&
                    !$this->is_granted($role))
                || (empty($matches) &&
                    !$this->request->isXMLHttpRequest() &&
                    !$this->is_granted(Role::ROLE_USER)
                )
            ) {
                $this->request->getSession()->set(
                    'message',
                    Message::AUTHENTICATION_REQUIRED
                );

                return false;
            }
        }

        return true;
    }
}