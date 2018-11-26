<?php

namespace Lib\Security;

use Lib\Http\Request;

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

        $this->storeRolesAndRelatedPaths();
    }

    /**
     * @param $uri
     * @return bool
     */
    public function isRouteAuthorized($uri)
    {
        if ($this->isFirewallActivated) {

            $role = null;

            // If no condition defined, it's open bar !!
            if (empty($this->roles_paths)) return true;

            $requiredRoleForUri = null;

            foreach ($this->roles_paths as $role => $path) {
                /* Getting the role(s) for this url */
                if (preg_match("#$path#", $uri, $matches)) {
                    $requiredRoleForUri = &$role;
                    break;
                }
            }
            
            if (empty($requiredRoleForUri)) {
                return true;
            }

            if ((!empty($requiredRoleForUri)
                && $requiredRoleForUri !== Role::ROLE_ANONYMOUS
                && !$this->is_granted($requiredRoleForUri)) ||
                (empty($matches)
                && !$this->request->isXMLHttpRequest()
                && !$this->is_granted(Role::ROLE_ANONYMOUS))) {

                return false;
            }
        }

        return true;
    }
}