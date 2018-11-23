<?php

namespace Lib\Security;

use Lib\Exception\Security\SecurityException;
use Lib\Http\Request;
use Lib\Utils\Message;

/**
 * Class Firewall
 * @package Lib\Security
 */
class Firewall extends Security
{
    /**
     * Firewall constructor.
     * @param Request $request
     * @param $securityFile
     * @throws \Exception
     */
    public function __construct(Request $request, $securityFile)
    {
        parent::__construct($request, $securityFile);

        try {
            $this->storeRolesAndRelatedPaths();
        } catch (SecurityException $securityException) {
            throw new \Exception($securityException->getMessage());
        }
    }

    /**
     * @param $uri
     * @return bool
     */
    public function isRouteAuthorized($uri)
    {
        if ($this->isFirewallActivated) {

            $role = null;

            if (empty($this->roles_paths)) return true;

            $requiredRole = null;

            foreach ($this->roles_paths as $role => $path) {
                /* Getting the role(s) for this url */
                if (preg_match("#$path#", $uri, $matches)) {
                    $requiredRole = &$role;
                    break;
                }
            }

            if ((!empty($requiredRole)
                && $requiredRole !== Role::ROLE_ANONYMOUS
                && !$this->is_granted($requiredRole)) ||
                (empty($matches)
                && !$this->request->isXMLHttpRequest()
                && !$this->is_granted(Role::ROLE_USER))) {

                $this->session->set(
                    'message',
                    Message::AUTHENTICATION_REQUIRED
                );

                return false;
            }
        }

        return true;
    }
}