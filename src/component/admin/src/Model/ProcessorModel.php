<?php

namespace Binary\Component\CmsMigrator\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Http\Http;

use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\User\UserHelper;
use Binary\Component\CmsMigrator\Administrator\Table\ArticleTable;

class ProcessorModel extends BaseDatabaseModel
{
    protected $sourceUrl;
    protected $http;
    protected $db;
    public function __construct(string $sourceUrl, Http $http, array $config = [])
    {
        $this->sourceUrl = rtrim($sourceUrl, '/');
        $this->http = $http;

        parent::__construct($config);
        $this->db = Factory::getDbo();
    }

    public function process(array $data): array
    {
        $result = [
            'success' => true,
            'imported' => 0,
            'errors' => []
        ];

        if (!isset($data['itemListElement']) || !is_array($data['itemListElement'])) {
            throw new \RuntimeException('Invalid WordPress JSON format');
        }

        try {
            $this->db->transactionStart();

            foreach ($data['itemListElement'] as $element) {
                try {
                    if (!isset($element['item'])) {
                        continue;
                    }

                    $article = $element['item'];
                    
                    // Get or create category
                    $categoryId = $this->getOrCreateCategory(
                        $article['articleSection'][0] ?? 'Uncategorized'
                    );

                    // Get or create author
                    $authorId = $this->getOrCreateUser(
                        $article['author']['name'] ?? 'admin'
                    );

                    // Create article
                    $articleTable = new ArticleTable($this->db);
                    
                    $articleData = [
                        'title' => $article['headline'],
                        'alias' => OutputFilter::stringURLSafe($article['headline']),
                        'content' => $this->cleanWordPressContent($article['articleBody']),
                        'state' => 1,
                        'catid' => $categoryId,
                        'created_by' => $authorId,
                        'created' => $this->formatDate($article['datePublished']),
                        'publish_up' => $this->formatDate($article['datePublished']),
                        'access' => 1,
                        'language' => '*',
                        'params' => '{}'
                    ];

                    if (!$articleTable->bind($articleData)) {
                        throw new \RuntimeException($articleTable->getError());
                    }

                    if (!$articleTable->check()) {
                        throw new \RuntimeException($articleTable->getError());
                    }

                    if (!$articleTable->store()) {
                        throw new \RuntimeException($articleTable->getError());
                    }

                    $result['imported']++;

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
            throw new \RuntimeException('Import failed: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Get or create category
     *
     * @param   string  $categoryName  Category name
     * @return  int     Category ID
     */
    protected function getOrCreateCategory(string $categoryName): int
    {
        $categoryTable = Table::getInstance('Category');
        
        // Try to find existing category
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from('#__categories')
            ->where([
                'extension = ' . $this->db->quote('com_cmsmigrator'),
                'title = ' . $this->db->quote($categoryName)
            ]);

        $categoryId = $this->db->setQuery($query)->loadResult();

        if (!$categoryId) {
            // Create new category
            $categoryData = [
                'title' => $categoryName,
                'alias' => OutputFilter::stringURLSafe($categoryName),
                'extension' => 'com_cmsmigrator',
                'published' => 1,
                'access' => 1,
                'params' => '{}',
                'metadata' => '{}',
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

            $categoryId = $categoryTable->id;
        }

        return (int) $categoryId;
    }

    /**
     * Get or create user
     *
     * @param   string  $username  Username
     * @return  int     User ID
     */
    protected function getOrCreateUser(string $username): int
    {
        $userTable = Table::getInstance('User');
        
        // Try to find existing user
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from('#__users')
            ->where('username = ' . $this->db->quote($username));

        $userId = $this->db->setQuery($query)->loadResult();

        if (!$userId) {
            // Create new user
            $userData = [
                'name' => $username,
                'username' => $username,
                'email' => strtolower($username) . '@example.com',
                'password' => UserHelper::hashPassword(UserHelper::genRandomPassword()),
                'block' => 0,
                'sendEmail' => 0,
                'registerDate' => Factory::getDate()->toSql(),
                'params' => '{}'
            ];

            if (!$userTable->bind($userData)) {
                throw new \RuntimeException($userTable->getError());
            }

            if (!$userTable->check()) {
                throw new \RuntimeException($userTable->getError());
            }

            if (!$userTable->store()) {
                throw new \RuntimeException($userTable->getError());
            }

            $userId = $userTable->id;
        }

        return (int) $userId;
    }

    /**
     * Clean WordPress content
     *
     * @param   string  $content  WordPress content
     * @return  string  Cleaned content
     */
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