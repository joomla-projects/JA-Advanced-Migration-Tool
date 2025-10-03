<?php

/**
 * @package     Migration Notice Module
 * @subpackage  mod_migrationnotice
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

// Determine alert class based on alert type
$alertClass = 'alert-' . $moduleData->alertType;
$moduleClass = htmlspecialchars($params->get('moduleclass_sfx', ''));

?>
<div class="migration-notice-module <?php echo $moduleClass; ?>">
    <?php if ($moduleData->noticeType === 'migration' || $moduleData->noticeType === 'both'): ?>
        <div class="alert <?php echo $alertClass; ?> migration-info">
            <h4 class="alert-heading">
                <span class="icon-info-circle" aria-hidden="true"></span>
                <?php echo Text::_('MOD_MIGRATIONNOTICE_MIGRATION_TITLE'); ?>
            </h4>
            
            <?php if ($moduleData->customMessage): ?>
                <p><?php echo nl2br(htmlspecialchars($moduleData->customMessage)); ?></p>
            <?php else: ?>
                <p><?php echo Text::_('MOD_MIGRATIONNOTICE_MIGRATION_MESSAGE'); ?></p>
            <?php endif; ?>
            
            <?php if ($moduleData->isGuest): ?>
                <p><?php echo Text::_('MOD_MIGRATIONNOTICE_GUEST_INFO'); ?></p>
                
                <?php if (isset($moduleData->loginUrl)): ?>
                    <p>
                        <a href="<?php echo $moduleData->loginUrl; ?>" class="btn btn-primary btn-sm">
                            <span class="icon-lock" aria-hidden="true"></span>
                            <?php echo Text::_('MOD_MIGRATIONNOTICE_LOGIN_BUTTON'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (($moduleData->noticeType === 'password' || $moduleData->noticeType === 'both') && !$moduleData->isGuest): ?>
        <div class="alert alert-warning password-reset-info">
            <h4 class="alert-heading">
                <span class="icon-key" aria-hidden="true"></span>
                <?php echo Text::_('MOD_MIGRATIONNOTICE_PASSWORD_TITLE'); ?>
            </h4>
            
            <p><?php echo Text::_('MOD_MIGRATIONNOTICE_PASSWORD_MESSAGE'); ?></p>
            
            <?php if ($moduleData->showResetLink && isset($moduleData->resetUrl)): ?>
                <hr>
                <p class="mb-0">
                    <a href="<?php echo $moduleData->resetUrl; ?>" class="btn btn-warning btn-sm">
                        <span class="icon-refresh" aria-hidden="true"></span>
                        <?php echo Text::_('MOD_MIGRATIONNOTICE_RESET_PASSWORD_BUTTON'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($moduleData->noticeType === 'both' && $moduleData->isGuest): ?>
        <div class="alert alert-info admin-info">
            <h5><?php echo Text::_('MOD_MIGRATIONNOTICE_ADMIN_TITLE'); ?></h5>
            <p class="small"><?php echo Text::_('MOD_MIGRATIONNOTICE_ADMIN_MESSAGE'); ?></p>
        </div>
    <?php endif; ?>
</div>
