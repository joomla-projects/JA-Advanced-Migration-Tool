<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_cmsmigrator
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Binary\Component\CmsMigrator\Administrator\View\Cpanel;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

/**
 * Cpanel View
 *
 * Provides the main control panel for the component.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Form object
     *
     * @var    Form
     * @since  1.0.0
     */
    public $form;

    /**
     * Document object
     *
     * @var    \Joomla\CMS\Document\HtmlDocument
     * @since  1.0.0
     */
    public $document;

    /**
     * Display the view
     *
     * @param   string|null  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null): void
    {
        // Load the form
        Form::addFormPath(JPATH_COMPONENT_ADMINISTRATOR . '/forms');

        $this->form = Form::getInstance('com_cmsmigrator.import', 'import', ['control' => 'jform']);

        if (!$this->form) {
            throw new \Exception(Text::_('COM_CMSMIGRATOR_FORM_NOT_FOUND'), 500);
        }

        // Get the document object
        $this->document = Factory::getDocument();

        // Load behaviors and custom scripts
        $this->addScripts();

        // Add toolbar
        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the toolbar
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_CMSMIGRATOR_MANAGER_CPANEL'));
        ToolbarHelper::custom('import.import', 'upload', 'upload', 'COM_CMSMIGRATOR_IMPORT_BUTTON', false);
    }

    /**
     * Adds necessary JavaScript behaviors and custom logic for the form
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function addScripts(): void
    {
        // Core Joomla behaviors
        HTMLHelper::_('behavior.formvalidator');
        HTMLHelper::_('behavior.keepalive');

        // Custom JavaScript for conditional field visibility and validation
        $script = "
        document.addEventListener('DOMContentLoaded', function() {
            const mediaToggle = document.querySelector('[name=\"jform[enable_media_migration]\"]');
            const storageMode = document.querySelector('[name=\"jform[media_storage_mode]\"]');
            const customDirRow = document.getElementById('media-custom-dir-row');
            const customDirInput = document.querySelector('[name=\"jform[media_custom_dir]\"]');
            const ftpFields = document.querySelectorAll('[name^=\"jform[ftp_\"]');
            const testFtpButton = document.getElementById('test-ftp-button');
            const testFtpResult = document.getElementById('test-ftp-result');

            function toggleFtpFields() {
                const isEnabled = mediaToggle && (mediaToggle.checked || mediaToggle.value === '1');
                ftpFields.forEach(function(field) {
                    field.closest('div.control-group, .control-wrapper, .field-box').style.display = isEnabled ? 'block' : 'none';
                });

                if (testFtpButton) {
                    testFtpButton.closest('.control-group, .control-wrapper, .field-box').style.display = isEnabled ? 'block' : 'none';
                }

                if (testFtpResult) {
                    testFtpResult.innerHTML = '';
                }
            }

            function toggleCustomDir() {
                if (storageMode && storageMode.value === 'custom') {
                    customDirRow.style.display = 'block';
                } else {
                    customDirRow.style.display = 'none';
                    if (customDirInput) customDirInput.value = '';
                }
            }

            if (mediaToggle) {
                mediaToggle.addEventListener('change', toggleFtpFields);
                toggleFtpFields(); // Initialize on load
            }

            if (storageMode) {
                storageMode.addEventListener('change', toggleCustomDir);
                toggleCustomDir();
            }

            const form = document.getElementById('migration-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const mediaEnabled = document.querySelector('[name=\"jform[enable_media_migration]\"]:checked');
                    if (mediaEnabled && mediaEnabled.value === '1') {
                        const ftpHost = document.querySelector('[name=\"jform[ftp_host]\"]').value;
                        const ftpUsername = document.querySelector('[name=\"jform[ftp_username]\"]').value;
                        const ftpPassword = document.querySelector('[name=\"jform[ftp_password]\"]').value;

                        if (!ftpHost || !ftpUsername || !ftpPassword) {
                            e.preventDefault();
                            alert('" . Text::_('COM_CMSMIGRATOR_MEDIA_FTP_FIELDS_REQUIRED') . "');
                        }
                    }
                });
            }
        });
        ";

        $this->document->addScriptDeclaration($script);
    }
}
