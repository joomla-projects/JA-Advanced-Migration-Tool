<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_cmsmigrator
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Binary\Component\CmsMigrator\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;

/**
 * Article Table
 *
 * Represents the database table for articles.
 *
 * @since  1.0.0
 */
class ArticleTable extends Table
{
    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  Database connector object.
     *
     * @since   1.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__cmsmigrator_articles', 'id', $db);
    }

    /**
     * Method to bind an associative array to the Table instance properties.
     *
     * @param   array  $array   Named array.
     * @param   mixed  $ignore  An optional array or space-separated list of properties to ignore while binding.
     *
     * @return  boolean  True on success.
     *
     * @since   1.0.0
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
     *
     * @since   1.0.0
     */
    public function check()
    {
        if (trim($this->title) == '') {
            throw new \RuntimeException('Article title is required');
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