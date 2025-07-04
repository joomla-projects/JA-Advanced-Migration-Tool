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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formContainer = document.getElementById('migration-form-container');
    const form = document.getElementById('migration-form');
    const statusContainer = document.getElementById('migration-status-container');
    const progressBar = document.getElementById('migration-progress-bar');
    const detailedStatus = document.getElementById('migration-detailed-status');
    let migrationStartTime = 0;

    if (form) {
        form.addEventListener('submit', function (e) {
            // Hide form and show progress
            formContainer.style.display = 'none';
            statusContainer.style.display = 'block';
            migrationStartTime = Math.floor(Date.now() / 1000); // Current time in seconds

            updateProgressBar(0, 'Starting migration...');
            startMigrationPolling();
        });
    }

    function updateProgressBar(percent, status) {
        progressBar.style.width = percent + '%';
        progressBar.textContent = percent + '%';
        progressBar.setAttribute('aria-valuenow', percent);
        detailedStatus.textContent = status;
    }

    function startMigrationPolling() {
        let interval = setInterval(function () {
            fetch('index.php?option=com_cmsmigrator&task=import.progress&format=raw')
                .then(response => response.json())
                .then(data => {
                    // Check if the progress data is from the current migration
                    if (data.timestamp && data.timestamp >= migrationStartTime) {
                        let percent = data.percent || 0;
                        let status = data.status || '';
                        updateProgressBar(percent, status);

                        if (percent >= 100) {
                            clearInterval(interval);
                            detailedStatus.textContent = 'Migration completed successfully!';
                        }
                    } else {
                        // If we get old data, keep showing the starting message
                        updateProgressBar(0, 'Starting migration...');
                    }
                })
                .catch(() => {
                    updateProgressBar(0, 'Unable to fetch progress');
                });
        }, 1000);
    }
});
</script>
