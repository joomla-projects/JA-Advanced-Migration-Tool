<?php

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

class ProcessorModel extends BaseDatabaseModel
{
    protected $db;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->db = Factory::getDbo();
    }

    public function process(array $data): array
    {
        if (isset($data['users']) && isset($data['post_types'])) {
            return $this->processJson($data);
        }

        if (isset($data['itemListElement'])) {
            return $this->processWordpress($data);
        }

        throw new \RuntimeException('Invalid data format');
    }

    private function processJson(array $data): array
    {
        $result = [
            'success' => true,
            'counts' => [
                'users' => 0,
                'taxonomies' => 0,
                'articles' => 0
            ],
            'errors' => []
        ];

        $this->db->transactionStart();

        try {
            $userMap = [];
            if (!empty($data['users'])) {
                $userResult = $this->processUsers($data['users']);
                $userMap = $userResult['map'];
                $result['counts']['users'] = $userResult['imported'];
                $result['errors'] = array_merge($result['errors'], $userResult['errors']);
            }
            
            $categoryMap = [];
            if (!empty($data['taxonomies'])) {
                $taxonomyResult = $this->processTaxonomies($data['taxonomies']);
                $categoryMap = $taxonomyResult['map'];
                $result['counts']['taxonomies'] = $taxonomyResult['imported'];
                $result['errors'] = array_merge($result['errors'], $taxonomyResult['errors']);
            }

            if (!empty($data['post_types'])) {
                foreach ($data['post_types'] as $postType => $posts) {
                    if ($postType === 'post' || $postType === 'page') {
                        $postResult = $this->processPosts($posts, $postType, $userMap, $categoryMap);
                        $result['counts']['articles'] += $postResult['imported'];
                        $result['errors'] = array_merge($result['errors'], $postResult['errors']);
                    }
                }
            }

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

        return $result;
    }

    private function processUsers(array $users): array
    {
        $result = ['imported' => 0, 'errors' => [], 'map' => []];
        $userModel = new UserModel(['ignore_request' => true]);

        foreach ($users as $userData) {
            try {
                // Check if user already exists
                $existingUserId = $this->getUserIdByUsername($userData['user_login']);
                if ($existingUserId) {
                    $result['map'][$userData['ID']] = $existingUserId;
                    continue;
                }

                $joomlaUser = [
                    'name' => $userData['display_name'],
                    'username' => $userData['user_login'],
                    'email' => $userData['user_email'],
                    'password' => UserHelper::hashPassword(UserHelper::genRandomPassword()),
                    'registerDate' => (new Date($userData['user_registered']))->toSql(),
                    'groups' => [2]
                ];

                $user = new \Joomla\CMS\User\User;
                $user->set('sendEmail', 0);
                $user->bind($joomlaUser);
                $user->requireReset = true;
                
                if (!$user->save()) {
                    throw new \RuntimeException($user->getError());
                }
                
                $result['map'][$userData['ID']] = $user->id;
                $result['imported']++;
            } catch (\Exception $e) {
                $result['errors'][] = sprintf('Error importing user "%s": %s', $userData['user_login'], $e->getMessage());
            }
        }

        return $result;
    }

    private function processTaxonomies(array $taxonomies): array
    {
        $result = ['imported' => 0, 'errors' => [], 'map' => []];
        $categoryModel = new CategoryModel(['ignore_request' => true]);

        foreach ($taxonomies as $taxonomyType => $terms) {
            if ($taxonomyType !== 'category' && $taxonomyType !== 'post_tag') continue;

            foreach ($terms as $term) {
                try {
                    $existingCatId = $this->getCategoryIdBySlug($term['slug']);
                    if ($existingCatId) {
                        $result['map'][$term['term_id']] = $existingCatId;
                        continue;
                    }
                    
                    $categoryData = [
                        'id' => 0,
                        'title' => $term['name'],
                        'alias' => $term['slug'],
                        'extension' => 'com_content',
                        'published' => 1,
                        'description' => $term['description'],
                        'language' => '*',
                    ];
                    
                    if (!$categoryModel->save($categoryData)) {
                        throw new \RuntimeException($categoryModel->getError());
                    }
                    $newCatId = $categoryModel->getItem()->id;
                    $result['map'][$term['term_id']] = $newCatId;

                    $result['imported']++;
                } catch (\Exception $e) {
                    $result['errors'][] = sprintf('Error importing category "%s": %s', $term['name'], $e->getMessage());
                }
            }
        }
        return $result;
    }
    
    private function processPosts(array $posts, string $postType, array $userMap, array $categoryMap): array
    {
        $result = ['imported' => 0, 'errors' => []];
        $articleModel = new ArticleModel(['ignore_request' => true]);
        
        $defaultCategoryId = $this->getDefaultCategoryId();

        foreach ($posts as $post) {
            try {
                $authorId = 42; // Fallback to admin
                if (isset($post['post_author']) && isset($userMap[$post['post_author']])) {
                    $authorId = $userMap[$post['post_author']];
                }

                $categoryId = $defaultCategoryId;
                if (!empty($post['terms']['category'])) {
                    $primaryCategory = reset($post['terms']['category']);
                    if (isset($categoryMap[$primaryCategory['term_id']])) {
                         $categoryId = $categoryMap[$primaryCategory['term_id']];
                    }
                }
                //For Now ignores post_parent but will consider...
                $articleData = [
                    'id' => 0,
                    'title' => $post['post_title'],
                    'alias' => $post['post_name'] ?? \Joomla\CMS\Filter\OutputFilter::stringURLSafe($post['post_title']),
                    'introtext' => $post['post_content'],
                    'fulltext' => '',
                    'state' => ($post['post_status'] === 'publish') ? 1 : 0,
                    'catid' => $categoryId,
                    'created' => (new Date($post['post_date']))->toSql(),
                    'created_by' => $authorId,
                    'publish_up' => (new Date($post['post_date']))->toSql(),
                    'language' => '*',
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

    private function getUserIdByUsername(string $username): int
    {
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from('#__users')
            ->where('username = ' . $this->db->quote($username));
        
        return (int) $this->db->setQuery($query)->loadResult();
    }

    private function getCategoryIdBySlug(string $slug): int
    {
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from('#__categories')
            ->where('alias = ' . $this->db->quote($slug))
            ->where('extension = ' . $this->db->quote('com_content'));

        return (int) $this->db->setQuery($query)->loadResult();
    }

    private function getDefaultCategoryId(): int
    {
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
            ->where($this->db->quoteName('path') . ' = ' . $this->db->quote('uncategorized'));
        return (int) $this->db->setQuery($query)->loadResult() ?: 2;
    }

    private function processWordpress(array $data): array
    {
        $result = [
            'success' => true,
            'counts' => [
                'users' => 0,
                'taxonomies' => 0,
                'articles' => 0
            ],
            'errors' => []
        ];

        if (!isset($data['itemListElement']) || !is_array($data['itemListElement'])) {
            $result['success'] = false;
            $result['errors'][] = 'Invalid WordPress JSON format';
            return $result;
        }

        $articleModel = new ArticleModel(['ignore_request' => true]);

        if (!$articleModel) {
            $result['success'] = false;
            $result['errors'][] = 'Could not get Article model';
            return $result;
        }

        try {
            $this->db->transactionStart();

            $defaultCategoryId = $this->getDefaultCategoryId();

            foreach ($data['itemListElement'] as $element) {
                try {
                    if (!isset($element['item'])) {
                        continue;
                    }

                    $article = $element['item'];

                    $content = $this->cleanWordPressContent($article['articleBody'] ?? '');

                    // Handle introtext/fulltext split
                    $introtext = $content;
                    $fulltext = '';
                    if (strpos($content, '<!--more-->') !== false) {
                        [$introtext, $fulltext] = explode('<!--more-->', $content, 2);
                    }

                    // Resolve category and author
                    $categoryId = $this->getOrCreateCategory($article['articleSection'][0] ?? 'Uncategorized', $result['counts']);
                    $authorName = $article['author']['name'] ?? 'admin';
                    $authorEmail = $article['author']['email'] ?? strtolower(str_replace(' ', '', $authorName)) . '@example.com';
                    $authorId = $this->getOrCreateUser($authorName, $authorEmail, $result['counts']);


                    // Build article data object
                    $articleData = [
                        'id'          => 0,
                        'title'       => $article['headline'],
                        'alias'       => OutputFilter::stringURLSafe($article['headline']),
                        'introtext'   => $introtext,
                        'fulltext'    => $fulltext,
                        'state'       => 1, // Published
                        'catid'       => $categoryId ?: $defaultCategoryId,
                        'created'     => $this->formatDate($article['datePublished'] ?? null),
                        'created_by'  => $authorId,
                        'created_by_alias' => $article['author']['name'] ?? '',
                        'publish_up'  => $this->formatDate($article['datePublished'] ?? null),
                        'language'    => '*',
                        'access'      => 1,
                        'featured'    => 0,
                        'metadata'    => [
                            'robots' => '',
                            'author' => $article['author']['name'] ?? '',
                            'rights' => '',
                            'xreference' => ''
                        ],
                        'images'      => '{}',
                        'urls'        => '{}',
                    ];

                    if (!$articleModel->save($articleData)) {
                        throw new \RuntimeException('Failed to save article: ' . $articleModel->getError());
                    }

                    $savedArticle = $articleModel->getItem();
                    $articleId = $savedArticle->id;

                    // Process custom fields if they exist
                    if (!empty($article['customFields']) && is_array($article['customFields'])) {
                        $this->processCustomFields($articleId, $article['customFields']);
                    }

                    $result['counts']['articles']++;

                } catch (\Exception $e) {
                    $result['errors'][] = sprintf(
                        'Error importing article "%s": %s',
                        $article['headline'] ?? 'Unknown',
                        $e->getMessage()
                    );
                }
            }

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

        return $result;
    }

    protected function getOrCreateCategory(string $categoryName, array &$counts): int
    {
        $categoryTable = Table::getInstance('Category');
        $alias = OutputFilter::stringURLSafe($categoryName);

        // Try to find existing category by alias
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from('#__categories')
            ->where([
                'extension = ' . $this->db->quote('com_content'),
                'alias = ' . $this->db->quote($alias)
            ]);

        $categoryId = $this->db->setQuery($query)->loadResult();

        if (!$categoryId) {
            // Create new category
            $categoryData = [
                'title' => $categoryName,
                'alias' => $alias,
                'extension' => 'com_content',
                'published' => 1,
                'access' => 1,
                'params' => [],
                'metadata' => [],
                'language' => '*'
            ];

            if (!$categoryTable->bind($categoryData)) {
                throw new \RuntimeException($categoryTable->getError());
            }

            if (!$categoryTable->check()) {
                throw new \RuntimeException($categoryTable->getError());
            }

            if (!$categoryTable->store()) {
                throw new \RuntimeException($categoryTable->getError());
            }

            $counts['taxonomies']++;
            $categoryId = $categoryTable->id;
        }

        return (int) $categoryId;
    }

    protected function getOrCreateUser(string $username, string $email, array &$counts): int
    {
        $userTable = Table::getInstance('User');

        $userId = UserHelper::getUserId($username);

        if (!$userId) {
            // Create new user
            $userData = [
                'name' => $username,
                'username' => $username,
                'email' => $email,
                'password' => UserHelper::hashPassword(UserHelper::genRandomPassword()),
                'block' => 0,
                'sendEmail' => 0,
                'registerDate' => Factory::getDate()->toSql(),
                'groups' => [2], // Registered
                'params' => []
            ];

            $user = new \Joomla\CMS\User\User;
            $user->set('sendEmail', 0);

            if (!$user->bind($userData)) {
                throw new \RuntimeException($user->getError());
            }

            if (!$user->save()) {
                throw new \RuntimeException($user->getError());
            }

            $counts['users']++;
            $userId = $user->id;
        }

        return (int) $userId;
    }

    protected function cleanWordPressContent(string $content): string
    {
        // Remove WordPress specific tags
        $content = preg_replace('/<!-- wp:.*?-->/', '', $content);
        $content = preg_replace('/<!-- \/wp:.*?-->/', '', $content);
        
        // Clean up HTML
        $content = strip_tags($content, '<p><a><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><img><hr><br>');
        
        return trim($content);
    }

    protected function formatDate(?string $dateString): string
    {
        if (empty($dateString)) {
            return Factory::getDate()->toSql();
        }

        try {
            $date = new Date($dateString);
            return $date->toSql();
        } catch (\Exception $e) {
            return Factory::getDate()->toSql();
        }
    }

    /**
     * Process custom fields for an article
     *
     * @param int   $articleId     The article ID
     * @param array $customFields  Array of custom field key-value pairs
     * @return void
     */
    protected function processCustomFields(int $articleId, array $customFields): void
    {
        foreach ($customFields as $fieldName => $fieldValue) {
            if (empty($fieldValue)) {
                continue;
            }

            try {
                // Get or create the custom field
                $fieldId = $this->getOrCreateCustomField($fieldName);
                
                if ($fieldId) {
                    // Save the field value for this article
                    $this->saveCustomFieldValue($fieldId, $articleId, $fieldValue);
                }
            } catch (\Exception $e) {
                // Log the error but continue processing other fields
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
     * @param string $fieldName The field name
     * @return int The field ID or 0 on failure
     */
    protected function getOrCreateCustomField(string $fieldName): int
    {
        // Check if field already exists
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from('#__fields')
            ->where([
                'context = ' . $this->db->quote('com_content.article'),
                'name = ' . $this->db->quote($fieldName),
                'state = 1'
            ]);

        $fieldId = (int) $this->db->setQuery($query)->loadResult();

        if (!$fieldId) {
            try {
                // Create new custom field
                $fieldModel = new FieldModel(['ignore_request' => true]);
                
                $fieldData = [
                    'id'          => 0,
                    'title'       => ucwords(str_replace(['_', '-'], ' ', $fieldName)),
                    'name'        => $fieldName,
                    'type'        => 'text', // Default to text field
                    'context'     => 'com_content.article',
                    'group_id'    => 0,
                    'description' => 'Imported from WordPress',
                    'state'       => 1,
                    'required'    => 0,
                    'only_use_in_subform' => 0,
                    'language'    => '*',
                    'default_value' => '',
                    'filter'      => 'safehtml',
                    'access'      => 1,
                    'params'      => [
                        'hint' => '',
                        'class' => '',
                        'label_class' => '',
                        'show_on' => '',
                        'render_class' => '',
                        'showlabel' => 1,
                        'label_render_class' => '',
                        'display' => 2,
                        'layout' => '',
                        'display_readonly' => 2
                    ]
                ];

                if ($fieldModel->save($fieldData)) {
                    $fieldId = $fieldModel->getItem()->id;
                }
            } catch (\Exception $e) {
                // If field creation fails, log it but don't break the import
                Factory::getApplication()->enqueueMessage(
                    sprintf('Failed to create custom field "%s": %s', $fieldName, $e->getMessage()),
                    'warning'
                );
                return 0;
            }
        }

        return $fieldId;
    }

    /**
     * Save custom field value for an article
     *
     * @param int    $fieldId    The field ID
     * @param int    $articleId  The article ID
     * @param string $value      The field value
     * @return bool
     */
    protected function saveCustomFieldValue(int $fieldId, int $articleId, string $value): bool
    {
        // Check if value already exists
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__fields_values')
            ->where([
                'field_id = ' . (int) $fieldId,
                'item_id = ' . (int) $articleId
            ]);

        $exists = (bool) $this->db->setQuery($query)->loadResult();

        if ($exists) {
            // Update existing value
            $query = $this->db->getQuery(true)
                ->update('#__fields_values')
                ->set('value = ' . $this->db->quote($value))
                ->where([
                    'field_id = ' . (int) $fieldId,
                    'item_id = ' . (int) $articleId
                ]);
        } else {
            // Insert new value
            $query = $this->db->getQuery(true)
                ->insert('#__fields_values')
                ->columns(['field_id', 'item_id', 'value'])
                ->values(implode(',', [
                    (int) $fieldId,
                    (int) $articleId,
                    $this->db->quote($value)
                ]));
        }

        try {
            $this->db->setQuery($query)->execute();
            return true;
        } catch (\Exception $e) {
            // Log the error for debugging
            Factory::getApplication()->enqueueMessage(
                'Custom field save error: ' . $e->getMessage(), 
                'warning'
            );
            return false;
        }
    }
} 