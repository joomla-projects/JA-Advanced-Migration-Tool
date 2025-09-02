<?php

namespace Joomla\CMS\MVC\Model;

/**
 * Mock class for BaseModel for testing
 */
class BaseModel
{
    public function __construct($config = [])
    {
        // Mock implementation
    }
}

/**
 * Mock class for BaseDatabaseModel for testing
 */
class BaseDatabaseModel extends BaseModel
{
    protected $dbo;
    
    public function __construct($config = [])
    {
        parent::__construct($config);
        if (isset($config['dbo'])) {
            $this->dbo = $config['dbo'];
        }
    }
    
    protected function getDatabase()
    {
        return $this->dbo;
    }
}
