<?php

namespace Binary\Component\CmsMigrator\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

class MediaModel extends BaseDatabaseModel
{
    protected $db;
    protected $ftpConnection;
    protected $mediaBaseUrl;
    protected $mediaBasePath;
    protected $downloadedFiles = [];
    protected $storageDir = 'imports'; // Default directory

    public function setStorageDirectory(string $dir = 'imports')
    {
        $this->storageDir = preg_replace('/[^a-zA-Z0-9_-]/', '', $dir) ?: 'imports';
        $this->mediaBaseUrl = Uri::root() . 'images/' . $this->storageDir . '/';
        $this->mediaBasePath = JPATH_ROOT . '/images/' . $this->storageDir . '/';
        if (!Folder::exists($this->mediaBasePath)) {
            Folder::create($this->mediaBasePath);
        }
    }

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->db = Factory::getDbo();
        $this->setStorageDirectory('imports'); // Default to imports
    }

    public function migrateMediaInContent(array $ftpConfig, string $content, string $sourceUrl = ''): string
    {
        if (empty($content)) {
            return $content;
        }

        $imageUrls = $this->extractImageUrls($content);

        if (empty($imageUrls)) {
            return $content;
        }

        if (!$this->connectFtp($ftpConfig)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_CMSMIGRATOR_MEDIA_FTP_CONNECTION_FAILED'), 'warning');
            return $content;
        }

        $updatedContent = $content;

        foreach ($imageUrls as $originalUrl) {
            try {
                $newUrl = $this->downloadAndProcessImage($originalUrl);
                if ($newUrl) {
                    $updatedContent = str_replace($originalUrl, $newUrl, $updatedContent);
                }
            } catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage(
                    sprintf('Error processing image %s: %s', $originalUrl, $e->getMessage()),
                    'warning'
                );
            }
        }

        $this->disconnectFtp();
        return $updatedContent;
    }

    protected function extractImageUrls(string $content): array
    {
        $imageUrls = [];

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if (strpos($url, 'data:') === 0) continue;
                $imageUrls[] = $url;
            }
        }

        preg_match_all('/https?:\/\/[^\/]+\/wp-content\/uploads\/[^\s"\'<>]+\.(jpg|jpeg|png|gif|webp)/i', $content, $wpMatches);
        if (!empty($wpMatches[0])) {
            $imageUrls = array_merge($imageUrls, $wpMatches[0]);
        }

        return array_unique($imageUrls);
    }

    protected function downloadAndProcessImage(string $imageUrl): ?string
    {
        $parsedUrl = parse_url($imageUrl);
        if (!$parsedUrl || empty($parsedUrl['path'])) {
            Factory::getApplication()->enqueueMessage("Invalid image URL: $imageUrl", 'warning');
            return null;
        }

        $uploadPath = $parsedUrl['path'];
        if (strpos($uploadPath, '/wp-content/uploads/') === false) {
            Factory::getApplication()->enqueueMessage("Not a WordPress upload path: $uploadPath", 'warning');
            return null;
        }

        $pathInfo = pathinfo($uploadPath);
        $resizedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-768x512.' . $pathInfo['extension'];
        $originalPath = $pathInfo['dirname'] . '/' . $pathInfo['basename'];

        // Try resized first, then original
        $candidatePaths = [
            'httpdocs' . $resizedPath,
            'httpdocs' . $originalPath
        ];

        foreach ($candidatePaths as $remotePath) {
            $localFileName = $this->getLocalFileName($remotePath);
            $localFilePath = $this->mediaBasePath . $localFileName;

            if (isset($this->downloadedFiles[$remotePath])) {
                return $this->downloadedFiles[$remotePath];
            }

            if (File::exists($localFilePath)) {
                $newUrl = $this->mediaBaseUrl . $localFileName;
                $this->downloadedFiles[$remotePath] = $newUrl;
                return $newUrl;
            }

            if ($this->downloadFileViaFtp($remotePath, $localFilePath)) {
                $newUrl = $this->mediaBaseUrl . $localFileName;
                $this->downloadedFiles[$remotePath] = $newUrl;

                Factory::getApplication()->enqueueMessage(
                    sprintf('✅ Downloaded image: %s', basename($remotePath)),
                    'info'
                );

                return $newUrl;
            }
        }

        Factory::getApplication()->enqueueMessage(
            "❌ Image not found in either resolution: $uploadPath",
            'warning'
        );

        return null;
    }

    protected function downloadFileViaFtp(string $remotePath, string $localPath): bool
    {
        if (!$this->ftpConnection) {
            return false;
        }

        $localDir = dirname($localPath);
        if (!Folder::exists($localDir)) {
            Folder::create($localDir);
        }

        $result = @ftp_get($this->ftpConnection, $localPath, $remotePath, FTP_BINARY);

        if ($result) {
            // Factory::getApplication()->enqueueMessage("✅ Downloaded: $remotePath", 'info');
            return true;
        }

        // Factory::getApplication()->enqueueMessage("❌ Failed to get: $remotePath", 'warning');
        return false;
    }

    protected function getLocalFileName(string $remotePath): string
    {
        $cleanPath = str_replace(['wp-content/uploads/', '/', '\\'], ['', '_', '_'], $remotePath);
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $cleanPath);
    }

    protected function connectFtp(array $config): bool
    {
        if ($this->ftpConnection) {
            return true;
        }

        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            Factory::getApplication()->enqueueMessage('FTP configuration incomplete', 'error');
            return false;
        }

        $this->ftpConnection = ftp_connect($config['host'], $config['port'] ?? 21, 15);
        ftp_set_option($this->ftpConnection, FTP_TIMEOUT_SEC, 10);

        if (!$this->ftpConnection) {
            Factory::getApplication()->enqueueMessage("Failed to connect to FTP server: {$config['host']}", 'error');
            return false;
        }

        $loginResult = ftp_login($this->ftpConnection, $config['username'], $config['password']);

        if (!$loginResult) {
            Factory::getApplication()->enqueueMessage('FTP login failed', 'error');
            ftp_close($this->ftpConnection);
            $this->ftpConnection = null;
            return false;
        }

        if (!empty($config['passive'])) {
            ftp_pasv($this->ftpConnection, true);
        }

        return true;
    }

    protected function disconnectFtp(): void
    {
        if ($this->ftpConnection) {
            ftp_close($this->ftpConnection);
            $this->ftpConnection = null;
        }
    }

    public function getMediaStats(): array
    {
        return [
            'downloaded' => count($this->downloadedFiles),
            'files' => array_keys($this->downloadedFiles)
        ];
    }

    /**
     * Test FTP connection without actually downloading files
     *
     * @param   array  $config  The FTP configuration
     * 
     * @return  array  Result containing success status and message
     */
    public function testConnection(array $config): array
    {
        $result = [
            'success' => false,
            'message' => ''
        ];

        // Validate configuration
        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            $result['message'] = Text::_('COM_CMSMIGRATOR_MEDIA_FTP_FIELDS_REQUIRED');
            return $result;
        }

        // Try to connect
        $connection = @ftp_connect($config['host'], $config['port'] ?? 21, 15);
        
        if (!$connection) {
            $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_FAILED', 'Could not connect to server');
            return $result;
        }

        // Try to login
        $loginResult = @ftp_login($connection, $config['username'], $config['password']);
        
        if (!$loginResult) {
            ftp_close($connection);
            $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_FAILED', 'Invalid credentials');
            return $result;
        }

        // Set passive mode if requested
        if (!empty($config['passive'])) {
            ftp_pasv($connection, true);
        }

        // Close the connection
        ftp_close($connection);
        
        // Return success
        $result['success'] = true;
        $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_SUCCESS', $config['host']);
        
        return $result;
    }

    public function clearCache(): void
    {
        $this->downloadedFiles = [];
    }

    public function __destruct()
    {
        $this->disconnectFtp();
    }
}
