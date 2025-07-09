<?php

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
                        <div id="ftp-config-section" style="display: none;">
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
                                            <button type="button" id="test-ftp-button" class="btn btn-primary">
                                                <span class="icon-plug" aria-hidden="true"></span>
                                                <?php echo Text::_('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION'); ?>
                                            </button>
                                            <div id="test-ftp-result" class="mt-2"></div>
                                        </div>
                                    </div>
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
                            <style>
                                #media-storage-mode-group .btn {
                                    min-width: 180px;
                                    font-weight: 500;
                                }
                                #media-storage-mode-group .btn.active, #media-storage-mode-group .btn:active {
                                    background: #007db0;
                                    color: #fff;
                                }
                                #media-storage-mode-group .btn-check:checked + .btn {
                                    background: #007db0;
                                    color: #fff;
                                }
                            </style>
                            <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const storageModeRadios = document.querySelectorAll('[name="jform[media_storage_mode]"]');
                                const customDirRow = document.getElementById('media-custom-dir-row');
                                const customDirInput = document.querySelector('[name="jform[media_custom_dir]"]');
                                function toggleCustomDir() {
                                    const selectedMode = document.querySelector('[name="jform[media_storage_mode]"]:checked');
                                    if (customDirRow) {
                                        if (selectedMode && selectedMode.value === 'custom') {
                                            customDirRow.style.display = 'block';
                                        } else {
                                            customDirRow.style.display = 'none';
                                            if (customDirInput) {
                                                customDirInput.value = '';
                                            }
                                        }
                                    }
                                    // Update button active state
                                    storageModeRadios.forEach(function(radio) {
                                        const label = document.querySelector('label[for="media_storage_mode_' + radio.value + '"]');
                                        if (label) {
                                            if (radio.checked) {
                                                label.classList.add('active');
                                            } else {
                                                label.classList.remove('active');
                                            }
                                        }
                                    });
                                }
                                if (storageModeRadios.length) {
                                    storageModeRadios.forEach(function(radio) {
                                        radio.addEventListener('change', toggleCustomDir);
                                    });
                                    toggleCustomDir();
                                }
                            });
                            </script>

                            <!-- Media Migration Info -->
                            <div class="alert alert-info mt-3">
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

    // FTP Test Connection functionality
    const testFtpButton = document.getElementById('test-ftp-button');
    const testFtpResult = document.getElementById('test-ftp-result');
    
    if (testFtpButton) {
        testFtpButton.addEventListener('click', function() {
            const host = document.querySelector('[name="jform[ftp_host]"]').value;
            const port = document.querySelector('[name="jform[ftp_port]"]').value;
            const username = document.querySelector('[name="jform[ftp_username]"]').value;
            const password = document.querySelector('[name="jform[ftp_password]"]').value;
            const passive = document.querySelector('[name="jform[ftp_passive]"]:checked').value;
            
            // Validate inputs
            if (!host || !username || !password) {
                testFtpResult.innerHTML = '<div class="alert alert-danger"><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_FTP_FIELDS_REQUIRED'); ?></div>';
                return;
            }
            
            // Show testing message
            testFtpResult.innerHTML = '<div class="alert alert-info"><?php echo Text::_('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_TESTING'); ?></div>';
            
            // Create form data
            const formData = new FormData();
            formData.append('host', host);
            formData.append('port', port);
            formData.append('username', username);
            formData.append('password', password);
            formData.append('passive', passive);
            formData.append('<?php echo Factory::getSession()->getFormToken(); ?>', 1);
            
            // Send AJAX request
            fetch('index.php?option=com_cmsmigrator&task=import.testFtp&format=raw', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    testFtpResult.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                } else {
                    testFtpResult.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                }
            })
            .catch(error => {
                testFtpResult.innerHTML = '<div class="alert alert-danger">Error testing connection: ' + error.message + '</div>';
            });
        });
    }

    // Show/hide FTP config based on enable_media_migration (works for checkbox, select, or radio)
    const enableMediaMigrationInputs = Array.from(
      document.getElementsByName('jform[enable_media_migration]')
    );
    const ftpConfigSection = document.getElementById('ftp-config-section');

    function isMediaEnabled() {
      if (enableMediaMigrationInputs.length === 0) {
        return false;
      }
      if (enableMediaMigrationInputs.length === 1) {
        const el = enableMediaMigrationInputs[0];
        return el.type === 'checkbox' ? el.checked : el.value === '1';
      }
      // radio list
      return enableMediaMigrationInputs.some(el => el.checked && el.value === '1');
    }

    function toggleFtpConfigSection() {
      if (isMediaEnabled()) {
        ftpConfigSection.style.display = '';
      } else {
        ftpConfigSection.style.display = 'none';
        if (testFtpResult) {
          testFtpResult.innerHTML = '';
        }
      }
    }

    enableMediaMigrationInputs.forEach(el =>
      el.addEventListener('change', toggleFtpConfigSection)
    );
    toggleFtpConfigSection();

    // Show/hide Custom Directory field based on Media Storage Location
    const storageModeRadios = document.querySelectorAll('[name="jform[media_storage_mode]"]');
    const customDirRow = document.getElementById('media-custom-dir-row');
    const customDirInput = document.querySelector('[name="jform[media_custom_dir]"]');

    function toggleCustomDir() {
        const selectedMode = document.querySelector('[name="jform[media_storage_mode]"]:checked');
        if (customDirRow) {
            if (selectedMode && selectedMode.value === 'custom') {
                customDirRow.style.display = 'block';
            } else {
                customDirRow.style.display = 'none';
                if (customDirInput) {
                    customDirInput.value = '';
                }
            }
        }
    }

    if (storageModeRadios.length) {
        storageModeRadios.forEach(function(radio) {
            radio.addEventListener('change', toggleCustomDir);
        });
        toggleCustomDir();
    }

    // Migration form submit + progress polling
    if (form) {
        form.addEventListener('submit', function (e) {
            formContainer.style.display = 'none';
            statusContainer.style.display = 'block';
            migrationStartTime = Math.floor(Date.now() / 1000);
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
                    if (data.timestamp && data.timestamp >= migrationStartTime) {
                        let percent = data.percent || 0;
                        let status = data.status || '';
                        updateProgressBar(percent, status);
                        if (percent >= 100) {
                            clearInterval(interval);
                            detailedStatus.textContent = 'Migration completed successfully!';
                        }
                    } else {
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
