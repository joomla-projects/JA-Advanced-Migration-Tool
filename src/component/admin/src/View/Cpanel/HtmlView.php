<?php

namespace Binary\Component\CmsMigrator\Administrator\View\Cpanel;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public $form;

    public function display($tpl = null): void
    {
        Form::addFormPath(JPATH_COMPONENT_ADMINISTRATOR . '/forms');

        $this->form = Form::getInstance('com_cmsmigrator.import', 'import', ['control' => 'jform']);

        if (!$this->form) {
            throw new \Exception(Text::_('COM_CMSMIGRATOR_FORM_NOT_FOUND'), 500);
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_CMSMIGRATOR_MANAGER_CPANEL'));
        ToolbarHelper::custom('import.import', 'upload', 'upload', 'COM_CMSMIGRATOR_IMPORT_BUTTON', false);
    }
}
