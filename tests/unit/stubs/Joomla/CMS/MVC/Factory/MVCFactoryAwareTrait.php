<?php

namespace Joomla\CMS\MVC\Factory;

/**
 * Stub for MVCFactoryAwareTrait
 */
trait MVCFactoryAwareTrait
{
    /**
     * The MVC factory
     *
     * @var MVCFactoryInterface
     */
    private $mvcFactory;

    /**
     * Get the MVC factory
     *
     * @return MVCFactoryInterface
     */
    public function getMVCFactory()
    {
        return $this->mvcFactory;
    }

    /**
     * Set the MVC factory
     *
     * @param   MVCFactoryInterface  $mvcFactory  The MVC factory
     *
     * @return  void
     */
    public function setMVCFactory(MVCFactoryInterface $mvcFactory)
    {
        $this->mvcFactory = $mvcFactory;
    }
}