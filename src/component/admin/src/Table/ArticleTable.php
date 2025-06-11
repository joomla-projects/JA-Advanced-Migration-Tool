<?php

namespace Binary\Component\CmsMigrator\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;

class ArticleTable extends Table
{
    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  Database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__cmsmigrator_articles', 'id', $db);
    }

    /**
     * Method to bind an associative array to the Table instance properties.
     *
     * @param   array  $array   Named array
     * @param   mixed  $ignore  An optional array or space separated list of properties to ignore while binding
     *
     * @return  boolean  True on success
     */
    public function bind($array, $ignore = '')
    {
        // Generate alias if not set
        if (empty($array['alias'])) {
            $array['alias'] = $array['title'];
        }
        $array['alias'] = OutputFilter::stringURLSafe($array['alias']);

        // Set created date if not set
        if (empty($array['created'])) {
            $array['created'] = Factory::getDate()->toSql();
        }

        return parent::bind($array, $ignore);
    }

    /**
     * Method to perform sanity checks on the Table instance properties to ensure they are safe to store in the database.
     *
     * @return  boolean  True if the instance is sane and able to be stored in the database.
     */
    public function check()
    {
        if (trim($this->title) == '') {
            $this->setError('Article title is required');
            return false;
        }

        if (trim($this->alias) == '') {
            $this->alias = $this->title;
        }

        $this->alias = OutputFilter::stringURLSafe($this->alias);

        if (trim($this->alias) == '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        return true;
    }
} 