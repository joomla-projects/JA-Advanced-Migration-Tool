<?php

/**
 * @package      Joomla.Administrator
 * @subpackage   com_cmsmigrator
 * @copyright    Copyright (C) 2025 Open Source Matters, Inc.
 * @license      GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Binary\Component\CmsMigrator\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\UserHelper;
use Joomla\Component\Users\Administrator\Model\UserModel;
use Joomla\Component\Categories\Administrator\Model\CategoryModel;
use Joomla\Component\Content\Administrator\Model\ArticleModel;
use Joomla\Component\Fields\Administrator\Model\FieldModel;
use Joomla\CMS\Filter\OutputFilter;
use Binary\Component\CmsMigrator\Administrator\Model\MediaModel;

/**
 * Processor Model
 *
 * Handles the processing of migration data.
 *
 * @since  1.0.0
 */
class ProcessorModel extends BaseDatabaseModel
{
    /**
     * Database object
     *
     * @var    \Joomla\Database\DatabaseDriver
     * @since  1.0.0
     */
    protected $db;

    /**
     * Constructor
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   1.0.0
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->db = Factory::getDbo();
    }

    /**
     * Processes migration data by routing to the appropriate processor.
     *
     * @param   array   $data                 The migration data.
     * @param   string  $sourceUrl            The source URL.
     * @param   array   $ftpConfig            FTP configuration.
     * @param   bool    $importAsSuperUser    Whether to import as a super user.
     *
     * @return  array   The result of the processing.
     * @throws  \RuntimeException  If the data format is invalid.
     */
    public function process(array $data, string $sourceUrl = '', array $ftpConfig = [], bool $importAsSuperUser = false): array
    {
        if (isset($data['users']) && isset($data['post_types'])) {
            return $this->processJson($data, $sourceUrl, $ftpConfig);
        }

        if (isset($data['itemListElement'])) {
            return $this->processWordpress($data, $sourceUrl, $ftpConfig, $importAsSuperUser);
        }

        throw new \RuntimeException('Invalid data format');
    }

    /**
     * Executes a given processing function within a database transaction.
     *
     * @param   callable $processor The function containing the import logic.
     * @param   array    &$result   The result array, passed by reference.
     *
     * @return  void
     */
    private function executeInTransaction(callable $processor, array &$result): void
    {
        $this->db->transactionStart();
        try {
            $processor();

            if (empty($result['errors'])) {
                $this->db->transactionCommit();
            } else {
                $this->db->transactionRollback();
                $result['success'] = false;
            }
        } catch (\Exception $e) {
            $this->db->transactionRollback();
            $result['success'] = false;
            $result['errors'][] = 'Import failed: ' . $e->getMessage();
        }
    }

    /**
     * Processes migration data from a generic JSON structure.
     *
     * @param   array   $data       The migration data.
     * @param   string  $sourceUrl  The source URL.
     * @param   array   $ftpConfig  FTP configuration.
     *
     * @return  array   The result of the processing.
     */
    private function processJson(array $data, string $sourceUrl = '', array $ftpConfig = []): array
    {
        $result = [
            'success' => true,
            'counts'  => ['users' => 0, 'taxonomies' => 0, 'articles' => 0, 'media' => 0, 'skipped' => 0],
            'errors'  => []
        ];

        $this->executeInTransaction(function () use ($data, $sourceUrl, $ftpConfig, &$result) {
            $mediaModel = $this->initializeMediaModel($ftpConfig);

            $userMap = [];
            if (!empty($data['users'])) {
                $userResult = $this->processUsers($data['users'], $result['counts']);
                $userMap = $userResult['map'];
                $result['errors'] = array_merge($result['errors'], $userResult['errors']);
            }
            
            $categoryMap = [];
            if (!empty($data['taxonomies'])) {
                $taxonomyResult = $this->processTaxonomies($data['taxonomies'], $result['counts']);
                $categoryMap = $taxonomyResult['map'];
                $result['errors'] = array_merge($result['errors'], $taxonomyResult['errors']);
            }

            if (!empty($data['post_types'])) {
                foreach ($data['post_types'] as $postType => $posts) {
                    if ($postType === 'post' || $postType === 'page') {
                        $postResult = $this->processPosts($posts, $userMap, $categoryMap, $mediaModel, $ftpConfig, $sourceUrl);
                        $result['counts']['articles'] += $postResult['imported'];
                        $result['counts']['skipped'] += $postResult['skipped'];
                        $result['errors'] = array_merge($result['errors'], $postResult['errors']);
                    }
                }
            }

            if ($mediaModel) {
                $result['counts']['media'] = $mediaModel->getMediaStats()['downloaded'];
            }
        }, $result);

        return $result;
    }

    /**
     * Processes migration data from a WordPress JSON-LD structure.
     *
     * @param   array   $data               The migration data.
     * @param   string  $sourceUrl          The source URL.
     * @param   array   $ftpConfig          FTP configuration.
     * @param   bool    $importAsSuperUser  Whether to import as a super user.
     *
     * @return  array   The result of the processing.
     */
    private function processWordpress(array $data, string $sourceUrl = '', array $ftpConfig = [], bool $importAsSuperUser = false): array
    {
        $result = [
            'success' => true,
            'counts'  => ['users' => 0, 'taxonomies' => 0, 'articles' => 0, 'media' => 0, 'skipped' => 0],
            'errors'  => []
        ];

        if (!isset($data['itemListElement']) || !is_array($data['itemListElement'])) {
            $result['success'] = false;
            $result['errors'][] = 'Invalid WordPress JSON format';
            return $result;
        }

        $this->executeInTransaction(function () use ($data, $sourceUrl, $ftpConfig, $importAsSuperUser, &$result) {
            $mediaModel = $this->initializeMediaModel($ftpConfig);
            $superUserId = $importAsSuperUser ? Factory::getUser()->id : null;
            $total = count($data['itemListElement']);
            
            foreach ($data['itemListElement'] as $index => $element) {
                $current = $index + 1;
                try {
                    if (isset($element['item'])) {
                        $this->processWordpressArticle($element['item'], $result, $mediaModel, $ftpConfig, $sourceUrl, $superUserId);
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = sprintf(
                        'Error importing article "%s": %s',
                        $element['item']['headline'] ?? 'Unknown',
                        $e->getMessage()
                    );
                }
                $percent = (int) (($current / $total) * 100);
                $this->updateProgress($percent, "Migrating articles: $current / $total (Imported: {$result['counts']['articles']}, Skipped: {$result['counts']['skipped']})");
            }

            if ($mediaModel) {
                $result['counts']['media'] = $mediaModel->getMediaStats()['downloaded'];
            }
        }, $result);

        if (!$result['success']) {
            $this->updateProgress(100, 'Migration failed!');
        }

        return $result;
    }

    /**
     * Processes a single WordPress article item.
     *
     * @param   array      $article        The article data array.
     * @param   array      &$result        The result array, passed by reference.
     * @param   ?MediaModel $mediaModel    The media model instance.
     * @param   array      $ftpConfig      FTP configuration.
     * @param   string     $sourceUrl      The source site URL.
     * @param   ?int       $superUserId    The ID of the super user to assign articles to.
     *
     * @return  void
     * @throws  \Exception
     */
    private function processWordpressArticle(array $article, array &$result, ?MediaModel $mediaModel, array $ftpConfig, string $sourceUrl, ?int $superUserId): void
    {
        if ($this->articleExists($article['headline'])) {
            $result['counts']['skipped']++;
            return;
        }

        // Clean and process content
        $content = $this->cleanWordPressContent($article['articleBody'] ?? '');
        if ($mediaModel) {
            $content = $mediaModel->migrateMediaInContent($ftpConfig, $content, $sourceUrl);
        }

        [$introtext, $fulltext] = (strpos($content, '') !== false)
            ? explode(',', $content, 2)
            : [$content, ''];

        // Resolve author and category
        if ($superUserId) {
            $authorId = $superUserId;
        } else {
            $authorName = $article['author']['name'] ?? 'admin';
            $authorEmail = $article['author']['email'] ?? strtolower(str_replace(' ', '', $authorName)) . '@example.com';
            $authorId = $this->getOrCreateUser($authorName, $authorEmail, $result['counts']);
        }
        $categoryId = $this->getOrCreateCategory($article['articleSection'][0] ?? 'Uncategorized', $result['counts']);

        // Prepare article data
        $articleData = [
            'id'               => 0,
            'title'            => $article['headline'],
            'alias'            => $this->getUniqueAlias(OutputFilter::stringURLSafe($article['headline'])),
            'introtext'        => $introtext,
            'fulltext'         => $fulltext,
            'state'            => 1, // Published
            'catid'            => $categoryId ?: $this->getDefaultCategoryId(),
            'created'          => $this->formatDate($article['datePublished'] ?? null),
            'created_by'       => $authorId,
            'created_by_alias' => $superUserId ? ($article['author']['name'] ?? '') : '',
            'publish_up'       => $this->formatDate($article['datePublished'] ?? null),
            'language'         => '*',
        ];

        // Save the article
        $articleModel = new ArticleModel(['ignore_request' => true]);
        if (!$articleModel->save($articleData)) {
            throw new \RuntimeException('Failed to save article: ' . $articleModel->getError());
        }
        $result['counts']['articles']++;

        // Process custom fields
        if (!empty($article['customFields']) && is_array($article['customFields'])) {
            $this->processCustomFields($articleModel->getItem()->id, $article['customFields']);
        }
    }

    /**
     * Processes a batch of users from the JSON import.
     *
     * @param   array $users   Array of user data.
     * @param   array &$counts The main counts array.
     *
     * @return  array  An array containing the user map and any errors.
     */
    private function processUsers(array $users, array &$counts): array
    {
        $result = ['errors' => [], 'map' => []];

        foreach ($users as $userData) {
            try {
                $joomlaUserId = $this->getOrCreateUser(
                    $userData['user_login'],
                    $userData['user_email'],
                    $counts,
                    $userData
                );
                $result['map'][$userData['ID']] = $joomlaUserId;
            } catch (\Exception $e) {
                $result['errors'][] = sprintf('Error importing user "%s": %s', $userData['user_login'], $e->getMessage());
            }
        }
        return $result;
    }

    /**
     * Processes a batch of taxonomies (categories) from the JSON import.
     *
     * @param   array $taxonomies Array of taxonomy data.
     * @param   array &$counts    The main counts array.
     *
     * @return  array An array containing the category map and any errors.
     */
    private function processTaxonomies(array $taxonomies, array &$counts): array
    {
        $result = ['errors' => [], 'map' => []];

        foreach ($taxonomies as $taxonomyType => $terms) {
            if ($taxonomyType !== 'category' && $taxonomyType !== 'post_tag') {
                continue;
            }
            foreach ($terms as $term) {
                try {
                    $newCatId = $this->getOrCreateCategory($term['name'], $counts, $term);
                    $result['map'][$term['term_id']] = $newCatId;
                } catch (\Exception $e) {
                    $result['errors'][] = sprintf('Error importing category "%s": %s', $term['name'], $e->getMessage());
                }
            }
        }
        return $result;
    }
    
    /**
     * Processes a batch of posts (articles) from the JSON import.
     *
     * @param   array      $posts       Array of post data.
     * @param   array      $userMap     Map of source user IDs to Joomla user IDs.
     * @param   array      $categoryMap Map of source category IDs to Joomla category IDs.
     * @param   ?MediaModel $mediaModel  The media model instance.
     * @param   array      $ftpConfig   FTP configuration.
     * @param   string     $sourceUrl   The source URL.
     *
     * @return  array Result of the post import.
     */
    private function processPosts(array $posts, array $userMap, array $categoryMap, ?MediaModel $mediaModel, array $ftpConfig, string $sourceUrl): array
    {
        $result = ['imported' => 0, 'errors' => [], 'skipped' => 0];
        $articleModel = new ArticleModel(['ignore_request' => true]);
        $defaultCategoryId = $this->getDefaultCategoryId();

        foreach ($posts as $post) {
            try {
                if ($this->articleExists($post['post_title'])) {
                    $result['skipped']++;
                    continue;
                }

                $authorId = $userMap[$post['post_author']] ?? 42; // Fallback to admin
                $content = $post['post_content'];

                // Assign category
                $categoryId = $defaultCategoryId;
                if (!empty($post['terms']['category'])) {
                    $primaryCategory = reset($post['terms']['category']);
                    if (isset($categoryMap[$primaryCategory['term_id']])) {
                        $categoryId = $categoryMap[$primaryCategory['term_id']];
                    }
                }
                
                // Migrate media
                if ($mediaModel) {
                    $content = $mediaModel->migrateMediaInContent($ftpConfig, $content, $sourceUrl);
                }

                $articleData = [
                    'id'         => 0,
                    'title'      => $post['post_title'],
                    'alias'      => $this->getUniqueAlias($post['post_name'] ?? OutputFilter::stringURLSafe($post['post_title'])),
                    'introtext'  => $content,
                    'state'      => ($post['post_status'] === 'publish') ? 1 : 0,
                    'catid'      => $categoryId,
                    'created'    => (new Date($post['post_date']))->toSql(),
                    'created_by' => $authorId,
                    'publish_up' => (new Date($post['post_date']))->toSql(),
                    'language'   => '*',
                ];

                if (!$articleModel->save($articleData)) {
                    throw new \RuntimeException($articleModel->getError());
                }
                $result['imported']++;
            } catch (\Exception $e) {
                $result['errors'][] = sprintf('Error importing post "%s": %s', $post['post_title'], $e->getMessage());
            }
        }
        return $result;
    }

    /**
     * Initializes the MediaModel if FTP configuration is provided.
     *
     * @param   array $ftpConfig FTP configuration.
     *
     * @return  ?MediaModel A MediaModel instance or null.
     */
    private function initializeMediaModel(array $ftpConfig): ?MediaModel
    {
        if (empty($ftpConfig['host'])) {
            return null;
        }

        $mediaModel = new MediaModel();
        $storageDir = (($ftpConfig['media_storage_mode'] ?? 'root') === 'custom' && !empty($ftpConfig['media_custom_dir']))
            ? $ftpConfig['media_custom_dir']
            : 'imports';
        $mediaModel->setStorageDirectory($storageDir);

        return $mediaModel;
    }

    // --- "Get or Create" Helper Methods ---

    /**
     * Gets an existing category ID by its name/alias or creates a new one.
     *
     * @param   string $categoryName The category name.
     * @param   array  &$counts      The counts array, passed by reference.
     * @param   ?array $sourceData   Optional array of source data (e.g., for slug, description).
     *
     * @return  int The category ID.
     * @throws  \RuntimeException If saving fails.
     */
    protected function getOrCreateCategory(string $categoryName, array &$counts, ?array $sourceData = null): int
    {
        $alias = $sourceData['slug'] ?? OutputFilter::stringURLSafe($categoryName);

        $query = $this->db->getQuery(true)
            ->select('id')
            ->from('#__categories')
            ->where('alias = ' . $this->db->quote($alias))
            ->where('extension = ' . $this->db->quote('com_content'));

        $categoryId = $this->db->setQuery($query)->loadResult();

        if (!$categoryId) {
            $categoryTable = Table::getInstance('Category');
            $categoryData = [
                'id'          => 0,
                'title'       => $categoryName,
                'alias'       => $alias,
                'description' => $sourceData['description'] ?? '',
                'extension'   => 'com_content',
                'published'   => 1,
                'access'      => 1,
                'language'    => '*',
            ];

            if (!$categoryTable->save($categoryData)) {
                throw new \RuntimeException($categoryTable->getError());
            }
            $counts['taxonomies']++;
            $categoryId = $categoryTable->id;
        }

        return (int) $categoryId;
    }

    /**
     * Gets an existing user ID by username or creates a new one.
     *
     * @param   string $username   The user's login name.
     * @param   string $email      The user's email.
     * @param   array  &$counts    The counts array, passed by reference.
     * @param   ?array $sourceData Optional array of source data (e.g., for display name, registration date).
     *
     * @return  int The user ID.
     * @throws  \RuntimeException If saving fails.
     */
    protected function getOrCreateUser(string $username, string $email, array &$counts, ?array $sourceData = null): int
    {
        $userId = UserHelper::getUserId($username);

        if (!$userId) {
            $user = new \Joomla\CMS\User\User;
            $userData = [
                'name'         => $sourceData['display_name'] ?? $username,
                'username'     => $username,
                'email'        => $email,
                'password'     => UserHelper::hashPassword(UserHelper::genRandomPassword()),
                'registerDate' => isset($sourceData['user_registered']) ? (new Date($sourceData['user_registered']))->toSql() : Factory::getDate()->toSql(),
                'groups'       => [2], // Registered
                'requireReset' => 1,
            ];

            $user->set('sendEmail', 0);

            if (!$user->bind($userData) || !$user->save()) {
                throw new \RuntimeException($user->getError());
            }

            $counts['users']++;
            $userId = $user->id;
        }

        return (int) $userId;
    }

    // --- Custom Fields & Utility Methods ---

    /**
     * Process custom fields for an article
     *
     * @param   int   $articleId     The article ID
     * @param   array $customFields  Array of custom field key-value pairs
     * @return  void
     */
    protected function processCustomFields(int $articleId, array $customFields): void
    {
        foreach ($customFields as $fieldName => $fieldValue) {
            if (empty($fieldValue)) {
                continue;
            }
            try {
                if ($fieldId = $this->getOrCreateCustomField($fieldName)) {
                    $this->saveCustomFieldValue($fieldId, $articleId, $fieldValue);
                }
            } catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage(
                    sprintf('Error processing custom field "%s": %s', $fieldName, $e->getMessage()),
                    'warning'
                );
            }
        }
    }

    /**
     * Get existing custom field ID or create a new one
     *
     * @param   string $fieldName The field name
     * @return  int The field ID or 0 on failure
     */
    protected function getOrCreateCustomField(string $fieldName): int
    {
        $alias   = OutputFilter::stringURLSafe($fieldName);
        $context = 'com_content.article';

        $query = $this->db->getQuery(true)
            ->select('id')
            ->from($this->db->quoteName('#__fields'))
            ->where('context = ' . $this->db->quote($context))
            ->where('name    = ' . $this->db->quote($fieldName));
        $existingId = (int) $this->db->setQuery($query)->loadResult();

        if ($existingId)
        {
            return $existingId;
        }

        $fieldModel = new FieldModel(['ignore_request' => true]);
        $fieldData  = [
            'id'          => 0,
            'title'       => ucwords(str_replace(['_', '-'], ' ', $fieldName)),
            'name'        => $fieldName,
            'alias'       => $alias,
            'type'        => 'text',
            'context'     => $context,
            'state'       => 1,
            'language'    => '*',
            'description' => '',
            'params'      => '',
        ];

        try
        {
            if (! $fieldModel->save($fieldData))
            {
                $err = $fieldModel->getError();
                if (strpos($err, 'COM_FIELDS_ERROR_UNIQUE_NAME') !== false)
                {
                    $query = $this->db->getQuery(true)
                        ->select('id')
                        ->from($this->db->quoteName('#__fields'))
                        ->where('context = ' . $this->db->quote($context))
                        ->where('name    = ' . $this->db->quote($fieldName));
                    return (int) $this->db->setQuery($query)->loadResult();
                }

                throw new \RuntimeException('Failed to create custom field: ' . $err);
            }

            return (int) $fieldModel->getItem()->id;
        }
        catch (\Exception $e)
        {
            Factory::getApplication()->enqueueMessage(
                sprintf('Error creating custom field "%s": %s', $fieldName, $e->getMessage()),
                'warning'
            );
            return 0;
        }
    }

    /**
     * Save custom field value for an article
     *
     * @param   int    $fieldId    The field ID
     * @param   int    $articleId  The article ID
     * @param   string $value      The field value
     * @return  void
     */
    protected function saveCustomFieldValue(int $fieldId, int $articleId, string $value): void
    {
        $fieldValue = new \stdClass();
        $fieldValue->field_id = $fieldId;
        $fieldValue->item_id  = $articleId;
        $fieldValue->value    = $value;

        $this->db->insertObject('#__fields_values', $fieldValue, ['field_id', 'item_id']);
    }

    /**
     * Gets the ID for the default 'Uncategorized' category.
     *
     * @return  int The category ID.
     */
    private function getDefaultCategoryId(): int
    {
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('path') . ' = ' . $this->db->quote('uncategorised'))
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'));
            
        return (int) $this->db->setQuery($query)->loadResult() ?: 2; // Fallback to root
    }

    /**
     * Cleans WordPress-specific block editor comments from content.
     *
     * @param   string $content The raw HTML content.
     *
     * @return  string The cleaned HTML content.
     */
    protected function cleanWordPressContent(string $content): string
    {
        $content = preg_replace('//s', '', $content);
        $content = preg_replace('//s', '', $content);
        return trim($content);
    }

    /**
     * Safely formats a date string into SQL format.
     *
     * @param   ?string $dateString The date string to format.
     *
     * @return  string The formatted SQL date.
     */
    protected function formatDate(?string $dateString): string
    {
        try {
            return (new Date($dateString ?: 'now'))->toSql();
        } catch (\Exception $e) {
            return Factory::getDate()->toSql();
        }
    }

    /**
     * Updates a progress file for the UI to monitor.
     *
     * @param   int    $percent The completion percentage.
     * @param   string $status  A status message.
     *
     * @return  void
     */
    private function updateProgress(int $percent, string $status = ''): void
    {
        $progressFile = JPATH_SITE . '/media/com_cmsmigrator/imports/progress.json';
        $data = ['percent' => $percent, 'status' => $status, 'timestamp' => time()];
        \Joomla\CMS\Filesystem\File::write($progressFile, json_encode($data));
    }

    /**
     * Generates a unique alias for a Joomla article.
     *
     * @param   string $alias The desired alias.
     *
     * @return  string A unique alias.
     */
    protected function getUniqueAlias(string $alias): string
    {
        $originalAlias = $alias;
        $counter = 1;

        while ($this->aliasExists($alias)) {
            $alias = $originalAlias . '-' . $counter++;
            if ($counter > 100) { // Safety break
                return $originalAlias . '-' . uniqid();
            }
        }
        return $alias;
    }
    
    /**
     * Checks if a given alias exists in the content table.
     *
     * @param   string $alias The alias to check.
     *
     * @return  bool True if the alias exists, false otherwise.
     */
    protected function aliasExists(string $alias): bool
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from('#__content')
            ->where('alias = ' . $this->db->quote($alias));

        return (bool) $this->db->setQuery($query)->loadResult();
    }

    /**
     * Checks if an article with a given title exists.
     *
     * @param   string $title The title to check.
     *
     * @return  bool True if the article exists.
     */
    protected function articleExists(string $title): bool
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from('#__content')
            ->where('title = ' . $this->db->quote($title));

        return (bool) $this->db->setQuery($query)->loadResult();
    }
}