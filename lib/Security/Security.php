<?php

namespace Lib\Security;

use Lib\Throwable\Security\SecurityException;
use Lib\Http\Request;
use Lib\Http\Session;
use Lib\Utils\Message;

/**
 * Class Security
 * @package Lib\Security
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

    /** @var string $securityFile */
    private $securityFile;

    /**
     * Security constructor.
     * @param Request $request
     * @param $securityFile
     */
    public function __construct(Request $request, $securityFile)
    {
        $this->request = $request;
        $this->securityFile = $securityFile;
        $this->session = $request->getSession();
    }

    /**
     * @throws SecurityException
     */
    public function storeRolesAndRelatedPaths()
    {
        $file = ROOT_DIR . '/' . $this->securityFile;

        /** @var array $securityFileContent */
        $securityFileContent = \Spyc::YAMLLoad($file);

        /** @var bool isFirewallActivated */
        $this->isFirewallActivated = $securityFileContent['firewall'];

        if (!isset($securityFileContent['access_control'])) {
            throw new SecurityException(
                sprintf(Message::UNDEFINED, 'access_control key')
            );
        }

        /** @var string|array $accessControl */
        $accessControl = $securityFileContent['access_control'];

        if (!empty($accessControl)) {
            foreach ($accessControl as $rolePaths) {
                $this->roles_paths[$rolePaths['roles']] = $rolePaths['path'];
            }
        }
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}