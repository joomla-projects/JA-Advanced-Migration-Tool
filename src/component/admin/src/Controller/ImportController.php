<?php

namespace Binary\Component\CmsMigrator\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class ImportController extends BaseController
{
    public function import()
    {
        $this->checkToken();
        // Retrieves form data (jform) and uploaded files.
        $app = Factory::getApplication();
        $input = $app->input;
        $jform = $input->get('jform', [], 'array');
        $files = $input->files->get('jform');

        $file = $files['import_file'] ?? null;
        $sourceCms = $jform['source_cms'] ?? null;
        $sourceUrl = $jform['source_url'] ?? null;
        
        //Ensures a file was uploaded and it was successful
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $app->enqueueMessage(Text::_('COM_CMSMIGRATOR_IMPORT_FILE_ERROR'), 'error');
            $this->setRedirect('index.php?option=com_cmsmigrator');
            return;
        }
        //Passes the data to ImportModel Function
        $model = $this->getModel('Import');

        if (!$model->import($file, $sourceCms, $sourceUrl)) {
            $app->enqueueMessage($model->getError(), 'error');
            $this->setRedirect('index.php?option=com_cmsmigrator');
            return;
        }

        // $app->enqueueMessage(Text::_('COM_CMSMIGRATOR_IMPORT_SUCCESS'), 'message');
        $this->setRedirect('index.php?option=com_cmsmigrator');
    }
} 