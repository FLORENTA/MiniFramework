<?php

namespace Entity;

class Dummy
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var integer $number
     */
    private $number;

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
     * @param integer $number
     *
     * @return $this
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * @return integer
     */
    public function getNumber()
    {
        return $this->number;
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

    /**
     * @manyToMany(target=Entity\Image, mappedBy=dummyImgs)
     */
    private $dummies_images = [];

    /**
     * @param Image $dummiesImage
     *
     * @return $this
     */
    public function addDummiesImage($dummiesImage)
    {
        $this->dummies_images[] = $dummiesImage;

        return $this;
    }

    /**
     * @return array
     */
    public function getDummiesImages()
    {
        return $this->dummies_images;
    }

}