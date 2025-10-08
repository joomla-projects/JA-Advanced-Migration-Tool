<?php

/**
 * @package     Migration Notice Module
 * @subpackage  mod_migrationnotice
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\MigrationNotice\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

/**
 * Dispatcher class for mod_migrationnotice
 *
 * @since  1.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    protected function getLayoutData()
    {
        // Get the default layout data from parent (includes params)
        $data = parent::getLayoutData();
        
        // Access the module parameters
        $params = $data['params'];
        
        // Check if the notice should be shown
        $showNotice = (bool) $params->get('show_notice', 1);
        
        if (!$showNotice) {
            return $data;
        }

        // Get the helper via HelperFactory
        $helper = $this->getHelperFactory()->getHelper('MigrationNoticeHelper');
        
        // Prepare module data
        $moduleData = $helper->getModuleData($params, $this->getApplication());

        // Add our custom data to the layout data
        $data['moduleData'] = $moduleData;

        return $data;
    }
}