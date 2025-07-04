<?php

namespace Binary\Component\CmsMigrator\Administrator\View\Cpanel;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    public $form;
    public $document;

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

    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_CMSMIGRATOR_MANAGER_CPANEL'));
        ToolbarHelper::custom('import.import', 'upload', 'upload', 'COM_CMSMIGRATOR_IMPORT_BUTTON', false);
    }

    /**
     * Adds necessary JavaScript behaviors and custom logic for the form
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
            const ftpFields = document.querySelectorAll('.ftp-field');

            function toggleFtpFields() {
                const isEnabled = mediaToggle && (mediaToggle.checked || mediaToggle.value === '1');
                ftpFields.forEach(function(field) {
                    field.closest('div.control-group, .control-wrapper, .field-box').style.display = isEnabled ? 'block' : 'none';
                });
            }

            if (mediaToggle) {
                mediaToggle.addEventListener('change', toggleFtpFields);
                toggleFtpFields(); // Initialize on load
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
