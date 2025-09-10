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

namespace Joomla\Database;

/**
 * Mock class for DatabaseQuery for testing
 */
class DatabaseQuery
{
    public function select($columns)
    {
        return $this;
    }
    
    public function from($table)
    {
        return $this;
    }
    
    public function where($condition)
    {
        return $this;
    }
    
    public function insert($table)
    {
        return $this;
    }
    
    public function values($values)
    {
        return $this;
    }
    
    public function update($table)
    {
        return $this;
    }
    
    public function set($sets)
    {
        return $this;
    }
    
    public function delete($table)
    {
        return $this;
    }
}
