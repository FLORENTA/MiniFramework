<?php

namespace Classes\Security;

use Classes\Http\Request;
use Classes\Http\Session;
use Classes\Utils\Message;

/**
 * Class Security
 * @package Classes\Security
 */
class Security
{
    use AuthorizationTrait;

    /** @var Request */
    protected $request;

    /** @var Session $session */
    protected $session;

    /** @var array  */
    protected $roles_paths = [];

    /** @var bool $isFirewallActivated */
    protected $isFirewallActivated = true;

    /**
     * Security constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->session = $request->getSession();
    }

    public function storeRolesAndRelatedPaths()
    {
        $file = ROOT_DIR . '/app/config/security.yml';

        $securityFileContent = \Spyc::YAMLLoad($file);

        $this->isFirewallActivated = $securityFileContent['firewall'];

        if (array_key_exists('access_control', $securityFileContent)) {
            /** @var null|array $accessControl */
            $accessControl = $securityFileContent['access_control'];

            if (!is_null($accessControl)) {
                foreach ($accessControl as $rolePaths) {
                    $this->roles_paths[$rolePaths['roles']] = $rolePaths['path'];
                }
            }
        } else {
            throw new \RuntimeException(sprintf(
                Message::UNDEFINED, 'access_control key')
            );
        }
    }

    public function getRequest()
    {
        return $this->request;
    }
}