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
use Joomla\Component\Tags\Administrator\Model\TagModel;
use Joomla\Component\Fields\Administrator\Model\FieldModel;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Uri\Uri;
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
     * @param   array   $ftpConfig            Connection configuration (FTP/FTPS/SFTP).
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
     * @param   array   $ftpConfig  Connection configuration (FTP/FTPS/SFTP).
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
            $tagMap = [];
            if (!empty($data['taxonomies'])) {
                $taxonomyResult = $this->processTaxonomies($data['taxonomies'], $result['counts']);
                $categoryMap = $taxonomyResult['map'];
                $tagMap = $taxonomyResult['tagMap'];
                $result['errors'] = array_merge($result['errors'], $taxonomyResult['errors']);
            }

            if (!empty($data['post_types'])) {
                foreach ($data['post_types'] as $postType => $posts) {
                    if ($postType === 'post' || $postType === 'page') {
                        $postResult = $this->processPosts($posts, $userMap, $categoryMap, $tagMap, $mediaModel, $ftpConfig, $sourceUrl, $result['counts']);
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

        if (!$result['success']) {
            $this->updateProgress(100, 'Migration failed!');
        } else {
            // Set completion status for successful migration
            $connectionType = $ftpConfig['connection_type'] ?? 'ftp';
            $completionMessage = sprintf(
                'Migration completed successfully! Imported: %d articles, %d media files%s',
                $result['counts']['articles'],
                $result['counts']['media'],
                $connectionType === 'zip' ? ' (from ZIP upload)' : ''
            );
            $this->updateProgress(100, $completionMessage);
        }

        return $result;
    }

    /**
     * Processes migration data from a WordPress JSON-LD structure.
     *
     * @param   array   $data               The migration data.
     * @param   string  $sourceUrl          The source URL.
     * @param   array   $ftpConfig          Connection configuration (FTP/FTPS/SFTP).
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
            
            // Process tags first if they exist in the data
            $tagMap = [];
            if (!empty($data['allTags']) && is_array($data['allTags'])) {
                $tagMap = $this->processWordpressTags($data['allTags'], $result['counts']);
            }
            
            $total = count($data['itemListElement']);
            
            // Use batch processing based on the number of articles
            $this->processBatchedWordpressArticles($data['itemListElement'], $result, $mediaModel, $ftpConfig, $sourceUrl, $superUserId, $total, $tagMap);

            if ($mediaModel) {
                $result['counts']['media'] = $mediaModel->getMediaStats()['downloaded'];
            }
        }, $result);

        if (!$result['success']) {
            $this->updateProgress(100, 'Migration failed!');
        } else {
            // Set completion status for successful migration
            $connectionType = $ftpConfig['connection_type'] ?? 'ftp';
            $completionMessage = sprintf(
                'Migration completed successfully! Imported: %d articles, %d taxonomies, %d media files%s',
                $result['counts']['articles'],
                $result['counts']['taxonomies'],
                $result['counts']['media'],
                $connectionType === 'zip' ? ' (from ZIP upload)' : ''
            );
            $this->updateProgress(100, $completionMessage);
        }

        return $result;
    }

    /**
     * Process WordPress articles in batches with parallel media downloading
     *
     * @param   array       $articles       Array of article elements
     * @param   array       &$result        Result array passed by reference
     * @param   ?MediaModel $mediaModel     Media model instance
     * @param   array       $ftpConfig      FTP configuration
     * @param   string      $sourceUrl      Source URL
     * @param   ?int        $superUserId    Super user ID
     * @param   int         $total          Total number of articles
     * @param   array       $tagMap         Map of tag slugs to tag IDs
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function processBatchedWordpressArticles(array $articles, array &$result, ?MediaModel $mediaModel, array $ftpConfig, string $sourceUrl, ?int $superUserId, int $total, array $tagMap = []): void
    {
        $batchSize = $this->calculateBatchSize($total);
        $batches = array_chunk($articles, $batchSize);
        $processedCount = 0;

        Factory::getApplication()->enqueueMessage(
            sprintf('Processing %d articles in %d batches (batch size: %d)', $total, count($batches), $batchSize),
            'info'
        );

        foreach ($batches as $batchIndex => $batch) {
            try {
                $this->processBatch($batch, $result, $mediaModel, $ftpConfig, $sourceUrl, $superUserId, $processedCount, $total, $batchIndex + 1, count($batches), $tagMap);
                $processedCount += count($batch);
            } catch (\Exception $e) {
                $result['errors'][] = sprintf('Error processing batch %d: %s', $batchIndex + 1, $e->getMessage());
            }
        }
    }

    /**
     * Calculate batch size based on total number of articles
     *
     * @param   int  $total  Total number of articles
     *
     * @return  int  Calculated batch size
     *
     * @since   1.0.0
     */
    private function calculateBatchSize(int $total): int
    {
        if ($total <= 25) {
            return $total; // Process all in one batch
        } elseif ($total <= 300) {
            return 25;
        } elseif ($total <= 1000) {
            return 50;
        } else {
            return 100; // Cap at 100 for large migrations to avoid nginx errors
        }
    }

    /**
     * Process a single batch of articles
     *
     * @param   array       $batch          Array of articles in this batch
     * @param   array       &$result        Result array passed by reference
     * @param   ?MediaModel $mediaModel     Media model instance
     * @param   array       $ftpConfig      FTP configuration
     * @param   string      $sourceUrl      Source URL
     * @param   ?int        $superUserId    Super user ID
     * @param   int         $processedCount Number of articles already processed
     * @param   int         $total          Total number of articles
     * @param   int         $batchNumber    Current batch number
     * @param   int         $totalBatches   Total number of batches
     * @param   array       $tagMap         Map of tag slugs to tag IDs
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function processBatch(array $batch, array &$result, ?MediaModel $mediaModel, array $ftpConfig, string $sourceUrl, ?int $superUserId, int $processedCount, int $total, int $batchNumber, int $totalBatches, array $tagMap = []): void
    {
        $this->updateProgress(
            (int)(($processedCount / $total) * 90),
            sprintf(
                "Processing batch %d of %d (%d articles) \n Importing articles: %d / %d (Imported: %d, Skipped: %d)",
                $batchNumber,
                $totalBatches,
                count($batch),
                $processedCount,
                $total,
                $result['counts']['articles'] ?? 0,
                $result['counts']['skipped'] ?? 0
            )
        );

        // Step 1: Extract all media URLs from the batch and update content references
        $batchData = [];
        $allMediaUrls = [];

        foreach ($batch as $element) {
            if (!isset($element['item'])) {
                continue;
            }

            $article = $element['item'];
            $content = $article['articleBody'] ?? '';
            
            if ($mediaModel && !empty($content)) {
                // Extract media URLs and prepare for batch download
                $mediaUrls = $mediaModel->extractImageUrlsFromContent($content);
                $updatedContent = $content;
                
                // Update content with planned Joomla URLs (before download)
                foreach ($mediaUrls as $originalUrl) {
                    $plannedUrl = $mediaModel->getPlannedJoomlaUrl($originalUrl);
                    if ($plannedUrl) {
                        $updatedContent = str_replace($originalUrl, $plannedUrl, $updatedContent);
                        $allMediaUrls[$originalUrl] = $plannedUrl;
                    }
                }
                
                $article['articleBody'] = $updatedContent;
            }
            
            $batchData[] = $article;
        }

        // Step 2: Download all media files in parallel (if any)
        if ($mediaModel && !empty($allMediaUrls)) {
            $mediaModel->batchDownloadMedia(array_keys($allMediaUrls), $ftpConfig);
        }

        // Step 3: Process articles sequentially
        foreach ($batchData as $index => $article) {
            try {
                $this->processWordpressArticle($article, $result, null, $ftpConfig, $sourceUrl, $superUserId, $tagMap);
                $currentProgress = (int)((($processedCount + $index + 1) / $total) * 90);
                $this->updateProgress(
                    $currentProgress,
                    sprintf(
                        "Processing batch %d of %d (%d articles) \n Importing articles: %d / %d (Imported: %d, Skipped: %d)",
                        $batchNumber,
                        $totalBatches,
                        count($batch),
                        $processedCount + $index + 1,
                        $total,
                        $result['counts']['articles'] ?? 0,
                        $result['counts']['skipped'] ?? 0
                    )
                );
            } catch (\Exception $e) {
                $result['errors'][] = sprintf('Error processing article "%s": %s', $article['headline'] ?? 'Unknown', $e->getMessage());
            }
        }
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
     * @param   array      $tagMap         Map of tag slugs to tag IDs.
     *
     * @return  void
     * @throws  \Exception
     */
    private function processWordpressArticle(array $article, array &$result, ?MediaModel $mediaModel, array $ftpConfig, string $sourceUrl, ?int $superUserId, array $tagMap = []): void
    {
        if ($this->articleExists($article['headline'])) {
            $result['counts']['skipped']++;
            return;
        }

        // Clean and process content
        $content = $this->cleanWordPressContent($article['articleBody'] ?? '');
        if ($mediaModel) {
            $content = $mediaModel->migrateMediaInContent($ftpConfig, $content, $sourceUrl);
        } else {
            // Convert WordPress URLs to Joomla URLs even when media migration is disabled
            $content = $this->convertWordPressUrlsToJoomla($content, is_array($ftpConfig) ? $ftpConfig : []);
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

        $articleId = $articleModel->getItem()->id;

        // Process custom fields
        if (!empty($article['customFields']) && is_array($article['customFields'])) {
            $this->processCustomFields($articleId, $article['customFields']);
        }

        // Process tags
        if (!empty($article['tags']) && is_array($article['tags']) && !empty($tagMap)) {
            $tagIds = [];
            foreach ($article['tags'] as $tagSlug) {
                if (isset($tagMap[$tagSlug])) {
                    $tagIds[] = $tagMap[$tagSlug];
                }
            }
            
            if (!empty($tagIds)) {
                $this->linkTagsToArticle($articleId, $tagIds);
            }
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
     * Processes a batch of taxonomies (categories and tags) from the JSON import.
     *
     * @param   array $taxonomies Array of taxonomy data.
     * @param   array &$counts    The main counts array.
     *
     * @return  array An array containing the category map, tag map and any errors.
     */
    private function processTaxonomies(array $taxonomies, array &$counts): array
    {
        $result = ['errors' => [], 'map' => [], 'tagMap' => []];

        foreach ($taxonomies as $taxonomyType => $terms) {
            if ($taxonomyType === 'category') {
                foreach ($terms as $term) {
                    try {
                        $newCatId = $this->getOrCreateCategory($term['name'], $counts, $term);
                        $result['map'][$term['term_id']] = $newCatId;
                    } catch (\Exception $e) {
                        $result['errors'][] = sprintf('Error importing category "%s": %s', $term['name'], $e->getMessage());
                    }
                }
            } elseif ($taxonomyType === 'post_tag') {
                foreach ($terms as $term) {
                    try {
                        $tagId = $this->getOrCreateTag($term['name'], $counts, $term);
                        $result['tagMap'][$term['term_id']] = $tagId;
                    } catch (\Exception $e) {
                        $result['errors'][] = sprintf('Error importing tag "%s": %s', $term['name'], $e->getMessage());
                    }
                }
            }
        }
        return $result;
    }
    
    /**
     * Processes WordPress tags from the allTags array in the JSON structure.
     *
     * @param   array $tags    Array of tag data with name, slug, and description.
     * @param   array &$counts The main counts array.
     *
     * @return  array Map of tag slugs to tag IDs.
     */
    private function processWordpressTags(array $tags, array &$counts): array
    {
        $tagMap = [];

        foreach ($tags as $tagData) {
            if (empty($tagData['name']) || empty($tagData['slug'])) {
                continue;
            }

            try {
                $tagId = $this->getOrCreateTag($tagData['name'], $counts, $tagData);
                $tagMap[$tagData['slug']] = $tagId;
            } catch (\Exception $e) {
                // Log the error but continue processing other tags
                Factory::getApplication()->enqueueMessage(
                    sprintf('Error importing tag "%s": %s', $tagData['name'], $e->getMessage()),
                    'warning'
                );
            }
        }

        return $tagMap;
    }
    
    /**
     * Processes a batch of posts (articles) from the JSON import.
     *
     * @param   array       $posts        Array of post data.
     * @param   array       $userMap      Map of source user IDs to Joomla user IDs.
     * @param   array       $categoryMap  Map of source category IDs to Joomla category IDs.
     * @param   array       $tagMap       Map of source tag IDs to Joomla tag IDs.
     * @param   ?MediaModel $mediaModel   The media model instance.
     * @param   array       $ftpConfig    FTP configuration.
     * @param   string      $sourceUrl    The source URL.
     * @param   array       &$counts      The main counts array passed by reference.
     *
     * @return  array Result of the post import.
     */
    private function processPosts(array $posts, array $userMap, array $categoryMap, array $tagMap, ?MediaModel $mediaModel, array $ftpConfig, string $sourceUrl, array &$counts): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        $totalPosts = count($posts);
        
        if ($totalPosts === 0) {
            return $result;
        }

        $batchSize = $this->calculateBatchSize($totalPosts);
        $batches = array_chunk($posts, $batchSize, true);
        $processedCount = 0;

        Factory::getApplication()->enqueueMessage(
            sprintf('Processing %d JSON posts in %d batches (batch size: %d)', $totalPosts, count($batches), $batchSize),
            'info'
        );

        foreach ($batches as $batchIndex => $batch) {
            try {
                $batchResult = $this->processJsonPostsBatch($batch, $userMap, $categoryMap, $tagMap, $mediaModel, $ftpConfig, $sourceUrl, $processedCount, $totalPosts, $batchIndex + 1, count($batches), $counts);
                $result['imported'] += $batchResult['imported'];
                $result['skipped'] += $batchResult['skipped'];
                $result['errors'] = array_merge($result['errors'], $batchResult['errors']);
                $processedCount += count($batch);
            } catch (\Exception $e) {
                $result['errors'][] = sprintf('Error processing JSON posts batch %d: %s', $batchIndex + 1, $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Process a single batch of JSON posts
     *
     * @param   array       $batch          Array of posts in this batch
     * @param   array       $userMap        Map of source user IDs to Joomla user IDs
     * @param   array       $categoryMap    Map of source category IDs to Joomla category IDs
     * @param   array       $tagMap         Map of source tag IDs to Joomla tag IDs
     * @param   ?MediaModel $mediaModel     Media model instance
     * @param   array       $ftpConfig      FTP configuration
     * @param   string      $sourceUrl      Source URL
     * @param   int         $processedCount Number of posts already processed
     * @param   int         $total          Total number of posts
     * @param   int         $batchNumber    Current batch number
     * @param   int         $totalBatches   Total number of batches
     * @param   array       &$counts        The main counts array passed by reference
     *
     * @return  array  Result of the batch processing
     *
     * @since   1.0.0
     */
    private function processJsonPostsBatch(array $batch, array $userMap, array $categoryMap, array $tagMap, ?MediaModel $mediaModel, array $ftpConfig, string $sourceUrl, int $processedCount, int $total, int $batchNumber, int $totalBatches, array &$counts): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        $articleModel = new ArticleModel(['ignore_request' => true]);
        $defaultCatId = $this->getDefaultCategoryId();

        $this->updateProgress(
            (int)(($processedCount / $total) * 90),
            sprintf('Processing JSON posts batch %d of %d (%d posts)', $batchNumber, $totalBatches, count($batch))
        );

        // Step 1: Extract all media URLs from the batch and update content references
        $batchData = [];
        $allMediaUrls = [];

        foreach ($batch as $postId => $post) {
            $content = $post['post_content'] ?? '';
            
            if ($mediaModel && !empty($content)) {
                // Extract media URLs and prepare for batch download
                $mediaUrls = $mediaModel->extractImageUrlsFromContent($content);
                $updatedContent = $content;
                
                // Update content with planned Joomla URLs (before download)
                foreach ($mediaUrls as $originalUrl) {
                    $plannedUrl = $mediaModel->getPlannedJoomlaUrl($originalUrl);
                    if ($plannedUrl) {
                        $updatedContent = str_replace($originalUrl, $plannedUrl, $updatedContent);
                        $allMediaUrls[$originalUrl] = $plannedUrl;
                    }
                }
                
                $post['post_content'] = $updatedContent;
            } elseif (!$mediaModel && !empty($content)) {
                // Convert WordPress URLs to Joomla URLs even when media migration is disabled
                $post['post_content'] = $this->convertWordPressUrlsToJoomla($content, is_array($ftpConfig) ? $ftpConfig : []);
            }
            
            $batchData[$postId] = $post;
        }

        // Step 2: Download all media files in parallel (if any)
        if ($mediaModel && !empty($allMediaUrls)) {
            $mediaModel->batchDownloadMedia(array_keys($allMediaUrls), $ftpConfig);
        }

        // Step 3: Process posts sequentially
        foreach ($batchData as $postId => $post) {
            try {
                if ($this->articleExists($post['post_title'])) {
                    $result['skipped']++;
                    continue;
                }

                $authorId = $userMap[$post['post_author']] ?? 42;
                $content = $post['post_content'];

                $catId = $defaultCatId;
                if (!empty($post['terms']['category'])) {
                    $primary = reset($post['terms']['category']);
                    if (isset($categoryMap[$primary['term_id']])) {
                        $catId = $categoryMap[$primary['term_id']];
                    }
                }

                $articleData = [
                    'id'         => 0,
                    'title'      => $post['post_title'],
                    'alias'      => $this->getUniqueAlias($post['post_name'] ?? OutputFilter::stringURLSafe($post['post_title'])),
                    'introtext'  => $content,
                    'state'      => ($post['post_status'] === 'publish') ? 1 : 0,
                    'catid'      => $catId,
                    'created'    => (new Date($post['post_date']))->toSql(),
                    'created_by' => $authorId,
                    'publish_up' => (new Date($post['post_date']))->toSql(),
                    'language'   => '*',
                ];

                if (!$articleModel->save($articleData)) {
                    throw new \RuntimeException($articleModel->getError());
                }

                $newId = $articleModel->getItem()->id;
                
                // Link tags to the article
                $tagIds = [];
                
                // Process tags from terms['post_tag'] (structured data)
                if (!empty($post['terms']['post_tag']) && !empty($tagMap)) {
                    foreach ($post['terms']['post_tag'] as $tag) {
                        if (isset($tagMap[$tag['term_id']])) {
                            $tagIds[] = $tagMap[$tag['term_id']];
                        }
                    }
                }
                
                // Process tags from tags_input (simple array) - create tags if they don't exist
                if (!empty($post['tags_input']) && is_array($post['tags_input'])) {
                    foreach ($post['tags_input'] as $tagName) {
                        if (!empty($tagName)) {
                            try {
                                $tagId = $this->getOrCreateTag($tagName, $counts);
                                $tagIds[] = $tagId;
                            } catch (\Exception $e) {
                                // Log error but continue with other tags
                                Factory::getApplication()->enqueueMessage(
                                    sprintf('Error creating tag "%s" for article "%s": %s', $tagName, $post['post_title'], $e->getMessage()),
                                    'warning'
                                );
                            }
                        }
                    }
                }
                
                // Link all collected tags to the article
                if (!empty($tagIds)) {
                    $this->linkTagsToArticle($newId, array_unique($tagIds));
                }
                
                if (!empty($post['metadata']) && is_array($post['metadata'])) {
                    $fields = [];
                    foreach ($post['metadata'] as $key => $vals) {
                        $fields[$key] = is_array($vals) ? implode(', ', $vals) : (string) $vals;
                    }
                    $this->processCustomFields($newId, $fields);
                }

                $result['imported']++;
            } catch (\Exception $e) {
                $result['errors'][] = sprintf('Error importing post "%s": %s', $post['post_title'], $e->getMessage());
            }

            $currentProgress = (int)((($processedCount + $result['imported'] + $result['skipped']) / $total) * 90);
            $this->updateProgress($currentProgress, sprintf('Processed JSON post %d of %d', $processedCount + $result['imported'] + $result['skipped'], $total));
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
        // Check if media migration is enabled
        $connectionType = $ftpConfig['connection_type'] ?? '';
        
        // For ZIP uploads, we don't need host credentials, just the connection type
        if ($connectionType === 'zip') {
            $mediaModel = new MediaModel();
            $storageDir = (($ftpConfig['media_storage_mode'] ?? 'root') === 'custom' && !empty($ftpConfig['media_custom_dir']))
                ? $ftpConfig['media_custom_dir']
                : 'imports';
            $mediaModel->setStorageDirectory($storageDir);
            
            // Process ZIP upload immediately when initializing the model
            if (!$mediaModel->connect($ftpConfig)) {
                Factory::getApplication()->enqueueMessage('Failed to process ZIP upload for media migration', 'error');
                return null;
            }
            
            return $mediaModel;
        }
        
        // For FTP/FTPS/SFTP, require host configuration
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

    /**
     * Converts WordPress media URLs to Joomla-compatible URLs
     * This allows users to manually copy media folders from WordPress to Joomla
     *
     * @param   string  $content     The content containing WordPress URLs
     * @param   array   $ftpConfig   FTP configuration to determine storage directory
     *
     * @return  string  The content with converted URLs
     *
     * @since   1.0.0
     */
    protected function convertWordPressUrlsToJoomla(string $content, array $ftpConfig = []): string
    {
        if (empty($content)) {
            return $content;
        }

        // Determine storage directory from FTP config
        $storageDir = (($ftpConfig['media_storage_mode'] ?? 'root') === 'custom' && !empty($ftpConfig['media_custom_dir']))
            ? $ftpConfig['media_custom_dir']
            : 'imports';

        // Get the Joomla site URL
        $joomlaBaseUrl = Uri::root();

        // Pattern to match WordPress media URLs
        // Matches: http://example.com/wp-content/uploads/2024/01/image.jpg
        $pattern = '/https?:\/\/[^\/]+\/wp-content\/uploads\/([^\s"\'<>]+\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|mp4|mp3|zip))/i';
        $updatedContent = preg_replace_callback($pattern, function($matches) use ($joomlaBaseUrl, $storageDir) {
            $wpPath = $matches[1]; // e.g., "2024/01/image.jpg"
            
            // Convert to Joomla URL maintaining the WordPress folder structure
            $joomlaUrl = $joomlaBaseUrl . 'images/' . $storageDir . '/' . $wpPath;
            
            return $joomlaUrl;
        }, $content);

        // Also handle relative WordPress URLs that might not have the full domain
        // Pattern: /wp-content/uploads/2024/01/image.jpg
        $relativePattern = '/\/wp-content\/uploads\/([^\s"\'<>]+\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|mp4|mp3|zip))/i';
        
        $updatedContent = preg_replace_callback($relativePattern, function($matches) use ($joomlaBaseUrl, $storageDir) {
            $wpPath = $matches[1]; // e.g., "2024/01/image.jpg"
            
            // Convert to Joomla URL maintaining the WordPress folder structure
            $joomlaUrl = $joomlaBaseUrl . 'images/' . $storageDir . '/' . $wpPath;
            
            return $joomlaUrl;
        }, $updatedContent);

        return $updatedContent;
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
     * Gets an existing tag ID by its name or creates a new one using Joomla's tag system.
     *
     * @param   string $tagName    The tag name.
     * @param   array  &$counts    The counts array, passed by reference.
     * @param   ?array $sourceData Optional array of source data (e.g., for slug, description).
     *
     * @return  int The tag ID.
     * @throws  \RuntimeException If saving fails.
     */
    protected function getOrCreateTag(string $tagName, array &$counts, ?array $sourceData = null): int
    {
        $alias = $sourceData['slug'] ?? OutputFilter::stringURLSafe($tagName);

        // Check if tag already exists
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from('#__tags')
            ->where('alias = ' . $this->db->quote($alias));

        $tagId = $this->db->setQuery($query)->loadResult();

        if (!$tagId) {
            // Get the root tag to use as parent
            $tagModel = new TagModel(['ignore_request' => true]);
            $tagData = [
                'id'          => 0,
                'title'       => $tagName,
                'alias'       => $alias,
                'description' => $sourceData['description'] ?? '',
                'published'   => 1,
                'access'      => 1,
                'language'    => '*',
            ];

            if (!$tagModel->save($tagData)) {
                // The model's save method will handle nested set logic automatically
                throw new \RuntimeException('Failed to save tag: ' . $tagModel->getError());
            }
            
            $counts['taxonomies']++;
            $tagId = $tagModel->getItem()->id;
        }

        return (int) $tagId;
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
     * Links tags to an article using Joomla's content-tag mapping system.
     *
     * @param   int   $articleId The article ID.
     * @param   array $tagIds    Array of tag IDs to link.
     *
     * @return  void
     * @since   1.0.0
     */
    protected function linkTagsToArticle(int $articleId, array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }

        // Remove existing tag mappings for this article
        $deleteQuery = $this->db->getQuery(true)
            ->delete('#__contentitem_tag_map')
            ->where('type_alias = ' . $this->db->quote('com_content.article'))
            ->where('content_item_id = ' . (int) $articleId);
        $this->db->setQuery($deleteQuery)->execute();

        // Add new tag mappings
        foreach ($tagIds as $tagId) {
            $mapping = new \stdClass();
            $mapping->type_alias = 'com_content.article';
            $mapping->core_content_id = $articleId;
            $mapping->content_item_id = $articleId;
            $mapping->tag_id = $tagId;
            $mapping->tag_date = Factory::getDate()->toSql();
            $mapping->type_id = 1; // Content type ID for articles

            try {
                $this->db->insertObject('#__contentitem_tag_map', $mapping);
            } catch (\Exception $e) {
                // Log error but don't fail the entire import
                Factory::getApplication()->enqueueMessage(
                    sprintf('Error linking tag %d to article %d: %s', $tagId, $articleId, $e->getMessage()),
                    'warning'
                );
            }
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