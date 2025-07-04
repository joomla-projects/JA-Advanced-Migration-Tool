<?php

namespace Binary\Component\CmsMigrator\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;

class ImportController extends BaseController
{
    public function import()
    {
        $this->checkToken();
        
        // Reset progress file at the start of new migration
        $progressFile = JPATH_SITE . '/media/com_cmsmigrator/imports/progress.json';
        if (file_exists($progressFile)) {
            File::delete($progressFile);
        }
        File::write($progressFile, json_encode([
            'percent' => 0,
            'status' => 'Starting migration...',
            'timestamp' => time()
        ]));
        
        // Retrieves form data (jform) and uploaded files.
        $app = Factory::getApplication();
        $input = $app->input;
        $jform = $input->get('jform', [], 'array');
        $files = $input->files->get('jform');

        $file = $files['import_file'] ?? null;
        $sourceCms = $jform['source_cms'] ?? null;
        $sourceUrl = $jform['source_url'] ?? null;
        
        // Get FTP configuration for media migration
        $ftpConfig = [];
        if (!empty($jform['enable_media_migration'])) {
            $ftpConfig = [
                'host' => $jform['ftp_host'] ?? '',
                'port' => (int) ($jform['ftp_port'] ?? 21),
                'username' => $jform['ftp_username'] ?? '',
                'password' => $jform['ftp_password'] ?? '',
                'passive' => !empty($jform['ftp_passive'])
            ];
        }
        
        //Ensures a file was uploaded and it was successful
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $app->enqueueMessage(Text::_('COM_CMSMIGRATOR_IMPORT_FILE_ERROR'), 'error');
            $this->setRedirect('index.php?option=com_cmsmigrator');
            return;
        }
        //Passes the data to ImportModel Function
        $model = $this->getModel('Import');

        if (!$model->import($file, $sourceCms, $sourceUrl, $ftpConfig)) {
            $app->enqueueMessage($model->getError(), 'error');
            $this->setRedirect('index.php?option=com_cmsmigrator');
            return;
        }

        // $app->enqueueMessage(Text::_('COM_CMSMIGRATOR_IMPORT_SUCCESS'), 'message');
        $this->setRedirect('index.php?option=com_cmsmigrator');
    }

    public function progress()
    {
        $progressFile = JPATH_SITE . '/media/com_cmsmigrator/imports/progress.json';
        if (file_exists($progressFile)) {
            header('Content-Type: application/json');
            echo file_get_contents($progressFile);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['percent' => 0, 'status' => 'Not started', 'timestamp' => time()]);
        }
        exit;
    }
} 