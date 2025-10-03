<?php

/**
 * @package     Migration Notice Module
 * @subpackage  mod_migrationnotice
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\MigrationNotice\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Joomla\Module\MigrationNotice\Site\Helper\MigrationNoticeHelper;

/**
 * Dispatcher class for mod_migrationnotice
 *
 * @since  1.0.0
 */
class Dispatcher implements DispatcherInterface
{
    /**
     * The module object
     *
     * @var    \stdClass
     *
     * @since  1.0.0
     */
    protected $module;

    /**
     * The application object
     *
     * @var    CMSApplicationInterface
     *
     * @since  1.0.0
     */
    protected $app;

    /**
     * The input object
     *
     * @var    Input
     *
     * @since  1.0.0
     */
    protected $input;

    /**
     * Constructor for Dispatcher
     *
     * @param   \stdClass                   $module        The module object
     * @param   CMSApplicationInterface     $app           The application object
     * @param   Input                       $input         The input object
     *
     * @since   1.0.0
     */
    public function __construct(\stdClass $module, CMSApplicationInterface $app, Input $input)
    {
        $this->module = $module;
        $this->app    = $app;
        $this->input  = $input;
    }

    /**
     * Dispatch a module
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function dispatch()
    {
        // Load the language file
        $language = $this->app->getLanguage();
        $language->load('mod_migrationnotice', JPATH_BASE . '/modules/mod_migrationnotice');

        // Get module parameters
        $params = new Registry($this->module->params);
        
        // Check if the notice should be shown
        $showNotice = (bool) $params->get('show_notice', 1);
        
        if (!$showNotice) {
            return;
        }

        // Get the helper directly
        $helper = new MigrationNoticeHelper();
        
        // Prepare module data
        $moduleData = $helper->getModuleData($params, $this->app);

        // Load the module CSS
        HTMLHelper::_('stylesheet', 'mod_migrationnotice/module.css', ['version' => 'auto', 'relative' => true]);

        // Include the template
        require ModuleHelper::getLayoutPath('mod_migrationnotice', $params->get('layout', 'default'));
    }
}