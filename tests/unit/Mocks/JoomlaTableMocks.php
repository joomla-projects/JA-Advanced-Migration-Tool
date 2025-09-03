<?php

namespace Joomla\CMS\Table;

/**
 * Mock class for Table for testing
 */
class Table
{
    protected $_tbl;
    protected $_tbl_key;
    protected $_db;
    
    public $id;
    public $title;
    public $alias;
    public $created;
    
    public function __construct($table, $key, $db)
    {
        $this->_tbl = $table;
        $this->_tbl_key = $key;
        $this->_db = $db;
    }
    
    public function getDatabase()
    {
        return $this->_db;
    }
    
    public function bind($array, $ignore = '')
    {
        foreach ($array as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        return true;
    }
    
    public function check()
    {
        return true;
    }
}
