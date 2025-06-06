<?php

namespace Binary\Component\CmsMigrator\Administrator\Model;

\defined('_JEXEC') or die;

use Binary\Component\CmsMigrator\Administrator\Event\MigrationEvent;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Http\HttpFactory;
//handles all the Mapping and Chain of Events logic as of Now.
class ImportModel extends BaseDatabaseModel
{
    protected $sourceUrl;
    protected $http;

    public function import($file, $sourceCms, $sourceUrl = '')
    {
        $this->sourceUrl = rtrim($sourceUrl, '/');
        $this->http = HttpFactory::getHttp();
        PluginHelper::importPlugin('migration');
        $dispatcher = Factory::getApplication()->getDispatcher();

        $event = new MigrationEvent('onMigrationConvert', ['sourceCms' => $sourceCms, 'filePath' => $file['tmp_name']]);
        $dispatcher->dispatch('onMigrationConvert', $event);
        $results = $event->getResults();

        // Find the first successful conversion
        $convertedData = null;
        foreach ($results as $result) {
            if ($result) {
                $convertedData = $result;
                break;
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
        
        // Now process the $data which is in the common schema.org format
        $this->processImport($data);


        return true;
    }

    protected function processImport(array $data)
    {
        if ($data['@type'] !== 'ItemList' || !isset($data['itemListElement'])) {
            $this->setError(Text::_('COM_CMSMIGRATOR_INVALID_ITEMLIST_FORMAT'));
            return false;
        }

        foreach ($data['itemListElement'] as $element) {
            if ($element['item']['@type'] === 'Article') {
                $this->importArticle($element['item']);
            }
        }

        return true;
    }

    protected function importArticle(array $articleData)
    {
        $articleTable = Table::getInstance('Content', 'Joomla\\Component\\Content\\Administrator\\Table');

        // Get the first category name, or use an empty string as a fallback for 'uncategorised'
        $categoryName = $articleData['articleSection'][0] ?? '';
        $authorName = $articleData['author']['name'] ?? '';
        $articleBody = $articleData['articleBody'] ?? '';

        // Process images if a source URL is provided
        if (!empty($this->sourceUrl)) {
            $articleBody = $this->processImages($articleBody);
        }

        $data = [];
        $data['title'] = $articleData['headline'] ?? 'Untitled Article';
        $data['articletext'] = $articleBody;
        $data['created'] = isset($articleData['datePublished']) ? (new Date($articleData['datePublished']))->toSql() : Factory::getDate()->toSql();
        $data['created_by'] = $this->getAuthorId($authorName);
        $data['state'] = 1;
        $data['language'] = '*';
        $data['catid'] = $this->getCategoryId($categoryName);


        if (!$articleTable->bind($data)) {
            $this->setError($articleTable->getError());
            return false;
        }

        if (!$articleTable->check()) {
            $this->setError($articleTable->getError());
            return false;
        }

        if (!$articleTable->store()) {
            $this->setError($articleTable->getError());
            return false;
        }

        Factory::getApplication()->enqueueMessage(Text::sprintf('COM_CMSMIGRATOR_ARTICLE_IMPORTED_SUCCESS', $data['title']));

        return true;
    }

    protected function processImages(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        preg_match_all('/<img[^>]+src="([^">]+)"/i', $text, $matches);

        if (empty($matches[1])) {
            return $text;
        }

        $destinationFolder = JPATH_SITE . '/images/imported_media';
        Folder::create($destinationFolder);

        foreach ($matches[1] as $originalSrc) {
            $imageUrl = $originalSrc;
            // Handle relative URLs
            if (strpos($imageUrl, 'http') !== 0) {
                if ($imageUrl[0] !== '/') {
                    $imageUrl = '/' . $imageUrl;
                }
                $imageUrl = $this->sourceUrl . $imageUrl;
            }

            try {
                $response = $this->http->get($imageUrl);

                if ($response->code !== 200) {
                    continue;
                }

                $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
                $destinationPath = $destinationFolder . '/' . $filename;

                if (File::write($destinationPath, $response->body)) {
                    $newSrc = 'images/imported_media/' . $filename;
                    $text = str_replace($originalSrc, $newSrc, $text);
                }
            } catch (\Exception $e) {
                // Could log the error, but for now we just skip the image
                continue;
            }
        }

        return $text;
    }

    protected function getAuthorId(string $authorName): int
    {
        if (empty($authorName)) {
            return Factory::getUser()->id;
        }

        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('username') . ' = ' . $db->quote($authorName) . ' OR ' . $db->quoteName('name') . ' = ' . $db->quote($authorName));

        $authorId = $db->setQuery($query)->loadResult();

        if ($authorId) {
            return (int) $authorId;
        }

        // Fallback to current user if no matching author is found
        return Factory::getUser()->id;
    }

    protected function getCategoryId(string $categoryName): int
    {
        // Use default 'Uncategorised' if no name is provided
        if (empty($categoryName)) {
            $uncategorisedTable = Table::getInstance('Category', 'Joomla\\Component\\Categories\\Administrator\\Table');
            if ($uncategorisedTable->load(['alias' => 'uncategorised', 'extension' => 'com_content'])) {
                return (int) $uncategorisedTable->id;
            }
            // Should not happen on a standard Joomla install
            return 1;
        }

        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('title') . ' = ' . $db->quote($categoryName))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'));
        
        $categoryId = $db->setQuery($query)->loadResult();

        if ($categoryId) {
            return (int) $categoryId;
        }

        // Category not found, create it as a top-level category
        $categoryTable = Table::getInstance('Category', 'Joomla\\Component\\Categories\\Administrator\\Table');
        $categoryData = [
            'title' => $categoryName,
            'alias' => \Joomla\CMS\Filter\OutputFilter::stringURLSafe($categoryName),
            'extension' => 'com_content',
            'published' => 1,
            'language' => '*',
            'access' => 1, // Public
            'parent_id' => 1, // Root
            'created_user_id' => Factory::getUser()->id,
        ];

        if (!$categoryTable->save($categoryData)) {
            $this->setError($categoryTable->getError());
            // Fallback to Uncategorised on failure
            return $this->getCategoryId('');
        }
        
        $categoryTable->rebuildPath($categoryTable->id);

        return (int) $categoryTable->id;
    }
} 