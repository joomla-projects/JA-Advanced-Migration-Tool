<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

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

                    <!-- FTP Configuration (shown when media migration is enabled) -->
                    <div id="ftp-config-section">
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
                            <div class="col-md-12">
                                <?php echo $this->form->renderField('ftp_passive'); ?>
                            </div>
                        </div>

                        <!-- Media Migration Info -->
                        <div class="alert alert-info">
                            <h5><span class="fa fa-info-circle" aria-hidden="true"></span> <?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_TITLE'); ?></h5>
                            <ul class="mb-0">
                                <li><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_RESOLUTION'); ?></li>
                                <li><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_FORMATS'); ?></li>
                                <li><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_LOCATION'); ?></li>
                                <li><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_INFO_FTP'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <input type="hidden" name="task" value="import.import"/>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            </div>
        </div>
    </div>
</div>
