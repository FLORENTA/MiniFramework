<?php

namespace Lib\Form;

/**
 * Class File
 * @package Lib\Form
 */
class File
{
    /** @var string $name */
    private $name;

    /** @var string $type */
    private $type;

    /** @var string $tmp_name */
    private $tmp_name;

    /** @var integer $error */
    private $error;

    /** @var integer $size */
    private $size;

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $tmp_name
     *
     * @return $this
     */
    public function setTmpName($tmp_name)
    {
        $this->tmp_name = $tmp_name;

        return $this;
    }

    /**
     * @return string
     */
    public function getTmpName()
    {
        return $this->tmp_name;
    }

    /**
     * @param integer $error
     *
     * @return $this
     */
    public function setError($error)
    {
        $this->error = $error;

        return $this;
    }

    /**
     * @return int
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param $size
     *
     * @return $this
     */
    public function setSize($size)
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        $args = explode('.', $this->name);

        return array_pop($args);
    }

    /**
     * @param string $filename
     * @param string $destination
     */
    public function moveTo($filename, $destination)
    {
        move_uploaded_file(
            $this->getTmpName(),
            ROOT_DIR . '/' . $destination . '/' . $filename
        );
    }
}