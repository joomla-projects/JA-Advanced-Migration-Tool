<?php

namespace Binary\Component\CmsMigrator\Administrator\Model;

\defined('_JEXEC') or die;

use Binary\Component\CmsMigrator\Administrator\Event\MigrationEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

//handles all the Mapping and Chain of Events logic as of Now.
class ImportModel extends BaseModel
{
    /**
     * Constructor
     *
     * @param   array  $config  An optional associative array of configuration settings
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
    }
    //Our importFunction doesn't know how to convert/parse this data
    public function import($file, $sourceCms, $sourceUrl = '', $ftpConfig = [], $importAsSuperUser = false)
    {

        // Handle JSON import directly, bypassing plugin event
        if ($sourceCms === 'json') {
            if (!isset($file['tmp_name']) || !is_readable($file['tmp_name'])) {
                $this->setError(Text::_('COM_CMSMIGRATOR_INVALID_FILE'));
                return false;
            }
            $convertedData = file_get_contents($file['tmp_name']);
            if (empty($convertedData)) {
                $this->setError(Text::_('COM_CMSMIGRATOR_EMPTY_JSON_FILE'));
                return false;
            }
        } else {
            PluginHelper::importPlugin('migration');
            $dispatcher = Factory::getApplication()->getDispatcher();

            // Fire the "onMigrationConvert" event to allow plugins to convert the file
            $event = new MigrationEvent('onMigrationConvert', ['sourceCms' => $sourceCms, 'filePath' => $file['tmp_name']]);
            $dispatcher->dispatch('onMigrationConvert', $event);

            //Get Results
            $results = $event->getResults();

            // Find the first successful conversion
            $convertedData = null;
            foreach ($results as $result) {
                if ($result) {
                    $convertedData = $result;
                    error_log('Converted Data: ' . $convertedData);
                    break;
                }
            }
        }

        // Save the converted data to a file (Optional)
        if ($convertedData) {
            $importPath = JPATH_SITE . '/media/com_cmsmigrator/imports';
            Folder::create($importPath);

            $fileName = 'import_' . $sourceCms . '_' . time() . '.json';
            $filePath = $importPath . '/' . $fileName;

            try {
                File::write($filePath, $convertedData);
                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_CMSMIGRATOR_JSON_SAVED', $filePath), 'message');
            } catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_CMSMIGRATOR_JSON_SAVE_FAILED', $e->getMessage()), 'error');
            }
        }

        if (!$convertedData) {
            $this->setError(Text::sprintf('COM_CMSMIGRATOR_NO_PLUGIN_FOUND', $sourceCms));
            return false;
        }

        $data = json_decode($convertedData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->setError(Text::_('COM_CMSMIGRATOR_INVALID_JSON_FORMAT_FROM_PLUGIN'));
            return false;
        }
        
        try {
            $mvcFactory = Factory::getApplication()->bootComponent('com_cmsmigrator')->getMVCFactory();
            $processor = $mvcFactory->createModel('Processor', 'Administrator', ['ignore_request' => true]);
            //Processor function to process data to Joomla Tables
            $result = $processor->process($data, $sourceUrl, $ftpConfig, $importAsSuperUser);
         
            if ($result['success']) {
                $message = Text::_('COM_CMSMIGRATOR_IMPORT_SUCCESS') . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_USERS_COUNT', $result['counts']['users']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_ARTICLES_COUNT', $result['counts']['articles']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_TAXONOMIES_COUNT', $result['counts']['taxonomies']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_MEDIA_COUNT', $result['counts']['media'] ?? 0) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_SKIPPED_COUNT', $result['counts']['skipped'] ?? 0);
                Factory::getApplication()->enqueueMessage($message, 'message');
            } else {
                $message = Text::_('COM_CMSMIGRATOR_IMPORT_PARTIAL') . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_USERS_COUNT', $result['counts']['users']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_ARTICLES_COUNT', $result['counts']['articles']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_TAXONOMIES_COUNT', $result['counts']['taxonomies']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_MEDIA_COUNT', $result['counts']['media'] ?? 0) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_SKIPPED_COUNT', $result['counts']['skipped'] ?? 0) . '<br>' .
                          implode("\n", $result['errors']);
                Factory::getApplication()->enqueueMessage($message, 'warning');
                return false;
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('COM_CMSMIGRATOR_IMPORT_ERROR', $e->getMessage()), 'error');
            return false;
        }

        return true;
    }
} 