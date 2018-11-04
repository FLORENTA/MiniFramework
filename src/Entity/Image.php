<?php

namespace Entity;

class Image
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var string $src
     */
    private $src;

    /**
     * @param integer $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $src
     *
     * @return $this
     */
    public function setSrc($src)
    {
        $this->src = $src;

        return $this;
    }

    /**
     * @return string
     */
    public function getSrc()
    {
        return $this->src;
    }

    /**
     * @oneToOne(target=Entity\Dummy, inversedBy=image)
     */
    private $dummy;

    /**
     * @param Dummy $dummy
     *
     * @return $this
     */
    public function setDummy($dummy)
    {
        $this->dummy = $dummy;

        return $this;
    }

    /**
     * @return Dummy
     */
    public function getDummy()
    {
        return $this->dummy;
    }

    /**
     * @manyToMany(target=Entity\User, inversedBy=images)
     * @joinTable(name=user_image)
     */
    private $users = [];

    /**
     * @param User $user
     *
     * @return $this
     */
    public function addUser($user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * @return array
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * @manyToMany(target=Entity\Dummy, inversedBy=dummies_images)
     * @joinTable(name=dummy_image)
     */
    private $dummyImgs = [];

    /**
     * @param Dummy $dummyImg
     *
     * @return $this
     */
    public function addDummyImg($dummyImg)
    {
        $this->dummyImgs[] = $dummyImg;

        return $this;
    }

    /**
     * @return array
     */
    public function getDummyImgs()
    {
        return $this->dummyImgs;
    }

}