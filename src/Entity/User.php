<?php

namespace Entity;

class User
{
    /**
     * User constructor.
     */
    public function __construct()
    {
        // Check PDO Fetch props late
        $date = date('Y-m-d H:i:s', time());
        $this->setCreatedAt($date);
        $this->setIsActive(0);
        $this->setRoles(['ROLE_USER']);
        $this->setIsAuthenticated(0);
    }

    /**
     * @var int $id
     */
    private $id;

    /**
     * @var string $username
     */
    private $username;

    /**
     * @var string $password
     */
    private $password;

    /**
     * @var string $email
     */
    protected $email;

    /**
     * @var bool $isActive
     */
    private $isActive;

    /**
     * @var $roles
     */
    private $roles;

    /**
     * @var string $token
     */
    private $token;

    /**
     * @var bool $isAuthenticated
     */
    protected $isAuthenticated;

    /**
     * @var \DateTime $createdAt
     */
    protected $createdAt;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $email
     * @return mixed
     */
    public function setEmail($email)
    {
        return $this->email = $email;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return bool
     */
    public function getIsActive()
    {
        return $this->isActive;
    }

    /**
     * @param bool $bool
     */
    public function setIsActive($bool)
    {
        $this->isActive = $bool;
    }

    /**
     * @param array $roles
     */
    public function setRoles($roles)
    {
        $this->roles = $roles;
    }

    /**
     * @return array
     */
    public function getRoles()
    {
        if (is_string($this->roles)) {
            return unserialize($this->roles);
        }

        return $this->roles;
    }

    /**
     * @param bool $bool
     */
    public function setIsAuthenticated($bool)
    {
        $this->isAuthenticated = $bool;
    }

    /**
     * @return bool
     */
    public function getIsAuthenticated()
    {
        return $this->isAuthenticated;
    }

    /**
     * @param string $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $date
     */
    public function setCreatedAt($date)
    {
        $this->createdAt = $date;
    }

    /**
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}