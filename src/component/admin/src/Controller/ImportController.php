<?php

namespace Binary\Component\CmsMigrator\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;
use Binary\Component\CmsMigrator\Administrator\Model\MediaModel;
use Joomla\CMS\Response\JsonResponse;

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
        $mediaStorageMode = $jform['media_storage_mode'] ?? 'root';
        $mediaCustomDir = $jform['media_custom_dir'] ?? '';
        if (!empty($jform['enable_media_migration'])) {
            // Validation: If custom directory is selected, it must not be empty
            if ($mediaStorageMode === 'custom' && empty($mediaCustomDir)) {
                $app->enqueueMessage(Text::_('COM_CMSMIGRATOR_MEDIA_CUSTOM_DIR_REQUIRED'), 'error');
                $this->setRedirect('index.php?option=com_cmsmigrator');
                return;
            }
            $ftpConfig = [
                'host' => $jform['ftp_host'] ?? '',
                'port' => (int) ($jform['ftp_port'] ?? 21),
                'username' => $jform['ftp_username'] ?? '',
                'password' => $jform['ftp_password'] ?? '',
                'passive' => !empty($jform['ftp_passive']),
                'media_storage_mode' => $mediaStorageMode,
                'media_custom_dir' => $mediaCustomDir
            ];
        }
        
        //Ensures a file was uploaded and it was successful
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $app->enqueueMessage(Text::_('COM_CMSMIGRATOR_IMPORT_FILE_ERROR'), 'error');
            $this->setRedirect('index.php?option=com_cmsmigrator');
            return;
        }
        $importAsSuperUser = !empty($jform['import_as_super_user']) && $jform['import_as_super_user'] == '1';
        //Passes the data to ImportModel Function
        $model = $this->getModel('Import');

        if (!$model->import($file, $sourceCms, $sourceUrl, $ftpConfig, $importAsSuperUser)) {
            $app->enqueueMessage($model->getError(), 'error');
            $this->setRedirect('index.php?option=com_cmsmigrator');
            return;
        }

        // $app->enqueueMessage(Text::_('COM_CMSMIGRATOR_IMPORT_SUCCESS'), 'message');
        $this->setRedirect('index.php?option=com_cmsmigrator');
    }

    /**
     * Test FTP connection
     *
     * @return void
     */
    public function testFtp()
    {
        // Check for request forgeries
        $this->checkToken();
        
        $app = Factory::getApplication();
        $input = $app->input;
        
        // Get FTP configuration
        $ftpConfig = [
            'host' => $input->getString('host', ''),
            'port' => $input->getInt('port', 21),
            'username' => $input->getString('username', ''),
            'password' => $input->getString('password', ''),
            'passive' => $input->getBool('passive', true)
        ];
        
        // Test connection
        $mediaModel = new MediaModel();
        $result = $mediaModel->testConnection($ftpConfig);
        
        // Send JSON response
        $app->setHeader('Content-Type', 'application/json');
        $app->sendHeaders();
        echo json_encode($result);
        $app->close();
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