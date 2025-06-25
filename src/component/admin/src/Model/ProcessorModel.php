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
} 