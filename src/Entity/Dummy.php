<?php

namespace Entity;

class Dummy
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var string $title
     */
    private $title;

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
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @oneToOne(target=Entity\Image, mappedBy=dummy)
     */
    private $image;

    /**
     * @param Image $image
     *
     * @return $this
     */
    public function setImage($image)
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return Image
     */
    public function getImage()
    {
        return $this->image;
    }

}