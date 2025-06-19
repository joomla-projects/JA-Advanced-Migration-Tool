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
        $result = ['success' => true, 'imported' => 0, 'errors' => []];

        $this->db->transactionStart();

        try {
            $userMap = [];
            if (!empty($data['users'])) {
                $userResult = $this->processUsers($data['users']);
                $userMap = $userResult['map'];
                $result['imported'] += $userResult['imported'];
                $result['errors'] = array_merge($result['errors'], $userResult['errors']);
            }
            
            $categoryMap = [];
            if (!empty($data['taxonomies'])) {
                $taxonomyResult = $this->processTaxonomies($data['taxonomies']);
                $categoryMap = $taxonomyResult['map'];
                $result['imported'] += $taxonomyResult['imported'];
                $result['errors'] = array_merge($result['errors'], $taxonomyResult['errors']);
            }

            if (!empty($data['post_types'])) {
                foreach ($data['post_types'] as $postType => $posts) {
                    if ($postType === 'post' || $postType === 'page') {
                        $postResult = $this->processPosts($posts, $postType, $userMap, $categoryMap);
                        $result['imported'] += $postResult['imported'];
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
        return ['success' => true, 'imported' => 0, 'errors' => ['WordPress import is not part of this feature.']];
    }
} 