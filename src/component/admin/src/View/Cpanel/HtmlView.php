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

        // Get the Web Asset Manager
        $wa = $this->document->getWebAssetManager();

        // Register and use custom assets
        $wa->useScript('core')
           ->useScript('com_cmsmigrator.init')
           ->useScript('com_cmsmigrator.admin')
           ->useStyle('com_cmsmigrator.admin')
           ->useScript('com_cmsmigrator.migration-form');

        // Add script options for translations (instead of direct text in JavaScript)
        $this->document->addScriptOptions('com_cmsmigrator.translations', [
            'ftpFieldsRequired' => Text::_('COM_CMSMIGRATOR_MEDIA_FTP_FIELDS_REQUIRED'),
            'zipFileError'      => Text::_('COM_CMSMIGRATOR_MEDIA_ZIP_FILE_ERROR')
        ]);

        // Load language strings for JavaScript
        Text::script('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_TESTING');
        Text::script('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_SUCCESS');
        Text::script('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_FAILED');
        Text::script('COM_CMSMIGRATOR_MEDIA_CONNECTION_FIELDS_REQUIRED');
    }
}
