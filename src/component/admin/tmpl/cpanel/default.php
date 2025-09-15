<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_cmsmigrator
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="fa fa-upload" aria-hidden="true"></span>
                    <?php echo Text::_('COM_CMSMIGRATOR_MIGRATION_SETUP'); ?>
                </h3>
            </div>
            <div class="card-body">
                <div id="migration-form-container">
                    <form action="<?php echo Route::_('index.php?option=com_cmsmigrator&task=import.import'); ?>" method="post"
                          name="adminForm" id="migration-form" class="form-validate" enctype="multipart/form-data">

                        <!-- Basic Migration Settings -->
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $this->form->renderField('source_cms'); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $this->form->renderField('import_file'); ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $this->form->renderField('source_url'); ?>
                            </div>
                        </div>

                        <!-- Import All Articles as Current Super User -->
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $this->form->renderField('import_as_super_user'); ?>
                            </div>
                        </div>

                        <!-- Media Migration Section -->
                        <hr>
                        <h4>
                            <span class="fa fa-images" aria-hidden="true"></span>
                            <?php echo Text::_('COM_CMSMIGRATOR_MEDIA_MIGRATION_SETTINGS'); ?>
                        </h4>
                        <p class="text-muted"><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_MIGRATION_DESC'); ?></p>

                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $this->form->renderField('enable_media_migration'); ?>
                            </div>
                        </div>

                        <!-- Connection Configuration (shown when media migration is enabled) -->
                        <div id="connection-config-section" style="display: none;">
                            <div class="row">
                                <div class="col-md-12">
                                    <?php echo $this->form->renderField('connection_type'); ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <?php echo $this->form->renderField('ftp_host'); ?>
                                </div>
                                <div class="col-md-6">
                                    <?php echo $this->form->renderField('ftp_port'); ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <?php echo $this->form->renderField('ftp_username'); ?>
                                </div>
                                <div class="col-md-6">
                                    <?php echo $this->form->renderField('ftp_password'); ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <?php echo $this->form->renderField('ftp_passive'); ?>
                                </div>
                                <div class="col-md-6">
                                    <div class="control-group">
                                        <div class="control-label">&nbsp;</div>
                                        <div class="controls">
                                            <button type="button" id="test-connection-button" class="btn btn-primary">
                                                <span class="icon-plug" aria-hidden="true"></span>
                                                <?php echo Text::_('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION'); ?>
                                            </button>
                                            <div id="test-connection-result" class="mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ZIP Upload Field -->
                            <div class="row">
                                <div class="col-md-12">
                                    <?php echo $this->form->renderField('media_zip_file'); ?>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="jform_media_storage_mode" class="form-label">
                                            <?php echo Text::_('COM_CMSMIGRATOR_FIELD_MEDIA_STORAGE_MODE_LABEL'); ?>
                                        </label>
                                        <div id="media-storage-mode-group" class="btn-group" role="group" aria-label="Media Storage Mode">
                                            <?php
                                            $mediaStorageOptions = $this->form->getField('media_storage_mode')->options;
                                            $selected = $this->form->getValue('media_storage_mode', null, 'root');
                                            foreach ($mediaStorageOptions as $option) :
                                                $isActive = ($selected == $option->value) ? 'active' : '';
                                                ?>
                                                <input type="radio" class="btn-check" name="jform[media_storage_mode]" id="media_storage_mode_<?php echo $option->value; ?>" value="<?php echo $option->value; ?>" autocomplete="off" <?php echo ($selected == $option->value) ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-primary <?php echo $isActive; ?>" for="media_storage_mode_<?php echo $option->value; ?>">
                                                    <?php echo $option->text; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        </br>
                                        <small class="form-text text-muted mt-1"><?php echo Text::_('COM_CMSMIGRATOR_FIELD_MEDIA_STORAGE_MODE_DESC'); ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="row" id="media-custom-dir-row" style="display:none;">
                                <div class="col-md-12">
                                    <?php echo $this->form->renderField('media_custom_dir'); ?>
                                </div>
                            </div>

                            <!-- Media Migration Info -->
                            <div class="alert alert-info mt-3">
                                <h5><span class="fa fa-info-circle" aria-hidden="true"></span> <?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_TITLE'); ?></h5>
                                <ul class="mb-0">
                                    <li><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_RESOLUTION'); ?></li>
                                    <li><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_FORMATS'); ?></li>
                                    <li><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_LOCATION'); ?></li>
                                    <li><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_FTP'); ?></li>
                                    <li><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_ZIP'); ?></li>
                                </ul>
                            </div>
                        </div>

                        <input type="hidden" name="task" value="import.import"/>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                </div>

                <!-- Migration Progress Section -->
                <div id="migration-status-container" style="display: none;">
                    <div class="text-center mb-4">
                        <h3><span class="fa fa-database" aria-hidden="true"></span> <?php echo Text::_('COM_CMSMIGRATOR_MIGRATING_DATA'); ?></h3>
                        <p class="lead"><?php echo Text::_('COM_CMSMIGRATOR_MIGRATING_DATA_DESC'); ?></p>
                    </div>

                    <div class="progress" style="height: 25px;">
                        <div id="migration-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>

                    <div class="text-center mt-4">
                        <div id="migration-detailed-status" class="h5 mb-3"></div>
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
