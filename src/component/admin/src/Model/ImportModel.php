<?php

namespace Binary\Component\CmsMigrator\Administrator\Model;

\defined('_JEXEC') or die;

use Binary\Component\CmsMigrator\Administrator\Event\MigrationEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Http\HttpFactory;
use Binary\Component\CmsMigrator\Administrator\Model\ProcessorModel;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;

//handles all the Mapping and Chain of Events logic as of Now.
class ImportModel extends BaseDatabaseModel
{
    //Our importFunction doesn't know how to convert/parse this data
    public function import($file, $sourceCms, $sourceUrl = '', $ftpConfig = [], $importAsSuperUser = false)
    {
         // Load plugins "migration"
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
            $processor = new ProcessorModel();
            //Processor function to process data to Joomla Tables
            $result = $processor->process($data, $sourceUrl, $ftpConfig, $importAsSuperUser);
         
            if ($result['success']) {
                $app = Factory::getApplication();
                $message = Text::_('COM_CMSMIGRATOR_IMPORT_SUCCESS') . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_USERS_COUNT', $result['counts']['users']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_ARTICLES_COUNT', $result['counts']['articles']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_TAXONOMIES_COUNT', $result['counts']['taxonomies']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_MEDIA_COUNT', $result['counts']['media'] ?? 0) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_SKIPPED_COUNT', $result['counts']['skipped'] ?? 0);
                $app->enqueueMessage($message, 'message');
            } else {
                $app = Factory::getApplication();
                $message = Text::_('COM_CMSMIGRATOR_IMPORT_PARTIAL') . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_USERS_COUNT', $result['counts']['users']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_ARTICLES_COUNT', $result['counts']['articles']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_TAXONOMIES_COUNT', $result['counts']['taxonomies']) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_MEDIA_COUNT', $result['counts']['media'] ?? 0) . '<br>' .
                          Text::sprintf('COM_CMSMIGRATOR_IMPORT_SKIPPED_COUNT', $result['counts']['skipped'] ?? 0) . '<br>' .
                          implode("\n", $result['errors']);
                $app->enqueueMessage($message, 'warning');
                return false;
            }
        } catch (\Exception $e) {
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::sprintf('COM_CMSMIGRATOR_IMPORT_ERROR', $e->getMessage()), 'error');
            return false;
        }

        return true;
    }
} 