<?php

namespace Joomla\CMS\MVC\Factory;

/**
 * Stub for MVCFactoryInterface
 */
interface MVCFactoryInterface
{
    /**
     * Method to create a model object.
     *
     * @param   string  $name    The name of the model.
     * @param   string  $prefix  Optional model prefix.
     * @param   array   $config  Optional array of configuration for the model.
     *
     * @return  object  The model object
     */
    public function createModel($name, $prefix = '', $config = []);

    /**
     * Method to create a view object.
     *
     * @param   string  $name    The name of the view.
     * @param   string  $prefix  Optional view prefix.
     * @param   string  $type    Optional type of view.
     * @param   array   $config  Optional array of configuration for the view.
     *
     * @return  object  The view object
     */
    public function createView($name, $prefix = '', $type = '', $config = []);

    /**
     * Method to create a controller object.
     *
     * @param   string  $name    The name of the controller.
     * @param   string  $prefix  Optional controller prefix.
     * @param   array   $config  Optional array of configuration for the controller.
     *
     * @return  object  The controller object
     */
    public function createController($name, $prefix = '', $config = []);

    /**
     * Method to create a table object.
     *
     * @param   string  $name    The name of the table.
     * @param   string  $prefix  Optional table prefix.
     * @param   array   $config  Optional array of configuration for the table.
     *
     * @return  object  The table object
     */
    public function createTable($name, $prefix = '', $config = []);
}