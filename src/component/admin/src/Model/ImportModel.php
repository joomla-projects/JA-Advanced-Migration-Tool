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
    public function import($file, $sourceCms, $sourceUrl = '')
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

        // Save the converted data to a file
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
        
        $http = HttpFactory::getHttp();
        $processor = new ProcessorModel($sourceUrl, $http);

        if (!$processor->process($data)) {
            $this->setError($processor->getError());
            return false;
        }

        return true;
    }
} 