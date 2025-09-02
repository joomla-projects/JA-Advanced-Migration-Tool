<?php

namespace Joomla\CMS\MVC\Controller;

/**
 * Mock class for BaseController for testing
 */
class BaseController
{
    protected $default_view = null;
    protected $app;
    protected $input;
    
    public function __construct($config = [], $factory = null, $app = null, $input = null)
    {
        $this->app = $app;
        $this->input = $input;
    }
    
    protected function checkToken()
    {
        // Mock implementation
    }
    
    protected function setRedirect($url, $msg = null, $type = null)
    {
        // Mock implementation
    }
    
    protected function getModel($name, $prefix = '', $config = [])
    {
        // Mock implementation
        return null;
    }
}
