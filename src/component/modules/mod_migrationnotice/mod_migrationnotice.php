<?php
/**
 * @package     Migration Notice Module
 * @subpackage  mod_migrationnotice
 * @copyright   Copyright (C) 2025 Joomla Academy. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// Get module parameters
$showNotice = (bool) $params->get('show_notice', 1);
$noticeType = $params->get('notice_type', 'both');
$alertType = $params->get('alert_type', 'info');
$showResetLink = (bool) $params->get('show_reset_link', 1);
$customMessage = $params->get('custom_message', '');

// Only display if notice is enabled
if (!$showNotice) {
    return;
}

// Get application
$app = Factory::getApplication();
$user = $app->getIdentity();

// Check if user is logged in (guest users see migration info, logged users see password reset info)
$isGuest = $user->guest;

// Prepare data for template
$moduleData = new stdClass();
$moduleData->showNotice = $showNotice;
$moduleData->noticeType = $noticeType;
$moduleData->alertType = $alertType;
$moduleData->showResetLink = $showResetLink;
$moduleData->customMessage = $customMessage;
$moduleData->isGuest = $isGuest;

// Generate password reset URL
if ($showResetLink) {
    $moduleData->resetUrl = Route::_('index.php?option=com_users&view=reset');
}

// Get login URL for guests
if ($isGuest) {
    $moduleData->loginUrl = Route::_('index.php?option=com_users&view=login');
}

// Load template
require ModuleHelper::getLayoutPath('mod_migrationnotice', $params->get('layout', 'default'));
