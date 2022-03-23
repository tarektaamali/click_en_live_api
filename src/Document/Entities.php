<?php

// src/Document/Entities.php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 */
class Entities
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $status;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $author;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $name;

    /**
     * @MongoDB\Field(type="date")
     */
    protected $dateCreation;

    /**
     * @MongoDB\Field(type="date")
     */
    protected $dateLastMmodif;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $mutex;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $parent;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $vues;

    /**
     * @MongoDB\Field(type="hash")
     */
    protected $extraPayload;

    /**
     * Get the value of id
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the value of status
     */ 
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the value of status
     *
     * @return  self
     */ 
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get the value of author
     */ 
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Set the value of author
     *
     * @return  self
     */ 
    public function setAuthor($author)
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Get the value of name
     */ 
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @return  self
     */ 
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the value of dateCreation
     */ 
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * Set the value of dateCreation
     *
     * @return  self
     */ 
    public function setDateCreation($dateCreation)
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get the value of dateLastMmodif
     */ 
    public function getDateLastMmodif()
    {
        return $this->dateLastMmodif;
    }

    /**
     * Set the value of dateLastMmodif
     *
     * @return  self
     */ 
    public function setDateLastMmodif($dateLastMmodif)
    {
        $this->dateLastMmodif = $dateLastMmodif;

        return $this;
    }

    /**
     * Get the value of mutex
     */ 
    public function getMutex()
    {
        return $this->mutex;
    }

    /**
     * Set the value of mutex
     *
     * @return  self
     */ 
    public function setMutex($mutex)
    {
        $this->mutex = $mutex;

        return $this;
    }

    /**
     * Get the value of parent
     */ 
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set the value of parent
     *
     * @return  self
     */ 
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get the value of vues
     */ 
    public function getVues()
    {
        return $this->vues;
    }

    /**
     * Set the value of vues
     *
     * @return  self
     */ 
    public function setVues($vues)
    {
        $this->vues = $vues;

        return $this;
    }

    /**
     * Get the value of extraPayload
     */ 
    public function getExtraPayload()
    {
        return $this->extraPayload;
    }

    /**
     * Set the value of extraPayload
     *
     * @return  self
     */ 
    public function setExtraPayload($extraPayload)
    {
        $this->extraPayload = $extraPayload;

        return $this;
    }
}