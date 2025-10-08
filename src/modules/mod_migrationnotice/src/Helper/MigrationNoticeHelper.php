<?php

/**
 * @package     Migration Notice Module
 * @subpackage  mod_migrationnotice
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\MigrationNotice\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/**
 * Helper for mod_migrationnotice
 *
 * @since  1.0.0
 */
class MigrationNoticeHelper
{
    /**
     * Get module data
     *
     * @param   Registry                 $params  Module parameters
     * @param   CMSApplicationInterface  $app     The application object
     *
     * @return  \stdClass
     *
     * @since   1.0.0
     */
    public function getModuleData(Registry $params, CMSApplicationInterface $app): \stdClass
    {
        // Get current user
        $user = $app->getIdentity();

        // Prepare module data object
        $moduleData = new \stdClass();
        $moduleData->showNotice    = (bool) $params->get('show_notice', 1);
        $moduleData->noticeType    = $params->get('notice_type', 'both');
        $moduleData->alertType     = $params->get('alert_type', 'info');
        $moduleData->showResetLink = (bool) $params->get('show_reset_link', 1);
        $moduleData->customMessage = $params->get('custom_message', '');
        $moduleData->isGuest       = $user->guest;

        // Generate URLs if needed
        if ($moduleData->showResetLink) {
            $moduleData->resetUrl = Route::_('index.php?option=com_users&view=reset');
        }

        if ($moduleData->isGuest) {
            $moduleData->loginUrl = Route::_('index.php?option=com_users&view=login');
        }

        return $moduleData;
    }
}