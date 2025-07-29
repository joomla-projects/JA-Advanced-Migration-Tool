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
        
        // Get media ZIP file if ZIP upload is selected
        $mediaZipFile = $files['media_zip_file'] ?? null;
        
        // Get connection configuration for media migration
        $connectionConfig = [];
        $mediaStorageMode = $jform['media_storage_mode'] ?? 'root';
        $mediaCustomDir = $jform['media_custom_dir'] ?? '';
        if (!empty($jform['enable_media_migration'])) {
            // Validation: If custom directory is selected, it must not be empty
            if ($mediaStorageMode === 'custom' && empty($mediaCustomDir)) {
                $app->enqueueMessage(Text::_('COM_CMSMIGRATOR_MEDIA_CUSTOM_DIR_REQUIRED'), 'error');
                $this->setRedirect('index.php?option=com_cmsmigrator');
                return;
            }
            
            $connectionType = $jform['connection_type'] ?? 'ftp';
            
            if ($connectionType === 'zip') {
                // Handle ZIP upload validation
                if (empty($mediaZipFile) || $mediaZipFile['error'] !== UPLOAD_ERR_OK) {
                    $app->enqueueMessage(Text::_('COM_CMSMIGRATOR_MEDIA_ZIP_FILE_ERROR'), 'error');
                    $this->setRedirect('index.php?option=com_cmsmigrator');
                    return;
                }
                
                $connectionConfig = [
                    'connection_type' => 'zip',
                    'zip_file' => $mediaZipFile,
                    'media_storage_mode' => $mediaStorageMode,
                    'media_custom_dir' => $mediaCustomDir
                ];
            } else {
                // Handle FTP/SFTP configuration
                $connectionConfig = [
                    'connection_type' => $connectionType,
                    'host' => $jform['ftp_host'] ?? '',
                    'port' => (int) ($jform['ftp_port'] ?? ($connectionType === 'sftp' ? 22 : 21)),
                    'username' => $jform['ftp_username'] ?? '',
                    'password' => $jform['ftp_password'] ?? '',
                    'passive' => !empty($jform['ftp_passive']),
                    'media_storage_mode' => $mediaStorageMode,
                    'media_custom_dir' => $mediaCustomDir
                ];
            }
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

        if (!$model->import($file, $sourceCms, $sourceUrl, $connectionConfig, $importAsSuperUser)) {
            $app->enqueueMessage($model->getError(), 'error');
            $this->setRedirect('index.php?option=com_cmsmigrator');
            return;
        }

        // $app->enqueueMessage(Text::_('COM_CMSMIGRATOR_IMPORT_SUCCESS'), 'message');
        $this->setRedirect('index.php?option=com_cmsmigrator');
    }

    /**
     * Test connection (FTP or SFTP)
     *
     * @return void
     */
    public function testConnection()
    {
        // Check for request forgeries
        $this->checkToken();
        
        $app = Factory::getApplication();
        $input = $app->input;
        
        // Get connection configuration
        $connectionConfig = [
            'connection_type' => $input->getString('connection_type', 'ftp'),
            'host' => $input->getString('host', ''),
            'port' => $input->getInt('port', 21),
            'username' => $input->getString('username', ''),
            'password' => $input->getString('password', ''),
            'passive' => $input->getBool('passive', true)
        ];
        
        // Test connection
        $mediaModel = new MediaModel();
        $result = $mediaModel->testConnection($connectionConfig);
        
        // Send JSON response
        $app->setHeader('Content-Type', 'application/json');
        $app->sendHeaders();
        echo json_encode($result);
        $app->close();
    }

    /**
     * Test FTP connection (legacy method for backward compatibility)
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
            'connection_type' => 'ftp',
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