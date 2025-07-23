<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_cmsmigrator
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Binary\Component\CmsMigrator\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * Media Model
 *
 * Handles media migration and processing.
 *
 * @since  1.0.0
 */
class MediaModel extends BaseDatabaseModel
{
    /**
     * Database object
     *
     * @var    \Joomla\Database\DatabaseDriver
     * @since  1.0.0
     */
    protected $db;

    /**
     * FTP connection resource
     *
     * @var    resource|null
     * @since  1.0.0
     */
    protected $ftpConnection;

    /**
     * Base URL for media
     *
     * @var    string
     * @since  1.0.0
     */
    protected $mediaBaseUrl;

    /**
     * Base path for media
     *
     * @var    string
     * @since  1.0.0
     */
    protected $mediaBasePath;

    /**
     * List of downloaded files
     *
     * @var    array
     * @since  1.0.0
     */
    protected $downloadedFiles = [];

    /**
     * Storage directory
     *
     * @var    string
     * @since  1.0.0
     */
    protected $storageDir = 'imports';

    /**
     * Remote document root directory
     *
     * @var    string
     * @since  1.0.0
     */
    protected $documentRoot = 'httpdocs';

    /**
     * Whether document root has been auto-detected
     *
     * @var    bool
     * @since  1.0.0
     */
    protected $documentRootDetected = false;

    /**
     * Sets the storage directory.
     * 
     * The storage directory will contain the WordPress media files organized 
     * in their original folder structure (e.g., 2024/01/image.jpg).
     *
     * @param   string  $dir  The directory name (e.g., 'imports', 'custom', etc.).
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function setStorageDirectory(string $dir = 'imports')
    {
        $this->storageDir = preg_replace('/[^a-zA-Z0-9_-]/', '', $dir) ?: 'imports';
        $this->mediaBaseUrl = Uri::root() . 'images/' . $this->storageDir . '/';
        $this->mediaBasePath = JPATH_ROOT . '/images/' . $this->storageDir . '/';
        if (!Folder::exists($this->mediaBasePath)) {
            Folder::create($this->mediaBasePath);
        }
    }

    /**
     * Sets the remote document root directory.
     *
     * @param   string  $documentRoot  The document root directory name.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function setDocumentRoot(string $documentRoot = 'httpdocs')
    {
        $this->documentRoot = trim($documentRoot, '/') ?: 'httpdocs';
    }

    /**
     * Gets the current document root directory.
     *
     * @return  string  The document root directory name.
     *
     * @since   1.0.0
     */
    public function getDocumentRoot(): string
    {
        return $this->documentRoot;
    }

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
        $this->setStorageDirectory('imports');
    }

    /**
     * Migrate media in content
     *
     * @param   array   $ftpConfig     The FTP configuration
     * @param   string  $content       The content with media URLs
     * @param   string  $sourceUrl     The source URL (optional)
     *
     * @return  string  The content with migrated media URLs
     *
     * @since   1.0.0
     */
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

        // Auto-detect document root on first use
        if (!$this->documentRootDetected) {
            $this->autoDetectDocumentRoot();
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

    /**
     * Extract image URLs from content
     *
     * @param   string  $content  The content to extract URLs from
     *
     * @return  array   An array of image URLs
     *
     * @since   1.0.0
     */
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

    /**
     * Download and process image
     *
     * @param   string  $imageUrl  The image URL to download and process
     *
     * @return  string|null  The new URL of the processed image, or null on failure
     *
     * @since   1.0.0
     */
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
            $this->documentRoot . $resizedPath,
            $this->documentRoot . $originalPath
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

    /**
     * Download file via FTP
     *
     * @param   string  $remotePath  The remote file path
     * @param   string  $localPath   The local file path
     *
     * @return  bool  True on success, false on failure
     *
     * @since   1.0.0
     */
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

    /**
     * Get local file path from remote path, preserving WordPress folder structure
     *
     * @param   string  $remotePath  The remote file path
     *
     * @return  string  The local file path relative to mediaBasePath
     *
     * @since   1.0.0
     */
    protected function getLocalFileName(string $remotePath): string
    {
        // Extract the path after wp-content/uploads/
        $pattern = '/.*\/wp-content\/uploads\/(.+)$/';
        if (preg_match($pattern, $remotePath, $matches)) {
            // Return the WordPress uploads structure (e.g., 2024/01/image.jpg)
            return $matches[1];
        }
        
        // Fallback: clean the path but preserve some structure
        $cleanPath = str_replace($this->documentRoot . '/', '', $remotePath);
        $cleanPath = str_replace('wp-content/uploads/', '', $cleanPath);
        
        // Sanitize directory and file names separately to preserve folder structure
        $pathParts = explode('/', $cleanPath);
        $sanitizedParts = array_map(function($part) {
            return preg_replace('/[^a-zA-Z0-9._-]/', '_', $part);
        }, $pathParts);
        
        return implode('/', $sanitizedParts);
    }

    /**
     * Auto-detect the document root directory
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function autoDetectDocumentRoot(): void
    {
        if (!$this->ftpConnection || $this->documentRootDetected) {
            return;
        }

        $commonRoots = ['httpdocs', 'public_html', 'www'];
        
        foreach ($commonRoots as $root) {
            if (@ftp_chdir($this->ftpConnection, $root)) {
                // Try to find wp-content directory to confirm this is the right root
                if (@ftp_chdir($this->ftpConnection, 'wp-content')) {
                    $this->documentRoot = $root;
                    $this->documentRootDetected = true;
                    
                    Factory::getApplication()->enqueueMessage(
                        "✅ Document root auto-detected: {$root}",
                        'info'
                    );
                    
                    // Return to original directory
                    @ftp_chdir($this->ftpConnection, '/');
                    return;
                }
                // Return to original directory if wp-content not found
                @ftp_chdir($this->ftpConnection, '/');
            }
        }
        
        // If no valid root found, use default and mark as detected to avoid repeated attempts
        $this->documentRootDetected = true;
        Factory::getApplication()->enqueueMessage(
            "⚠️ Could not auto-detect document root. Using default: {$this->documentRoot}",
            'warning'
        );
    }

    /**
     * Connect to FTP server
     *
     * @param   array  $config  The FTP configuration
     *
     * @return  bool  True on success, false on failure
     *
     * @since   1.0.0
     */
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

    /**
     * Disconnect from FTP server
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function disconnectFtp(): void
    {
        if ($this->ftpConnection) {
            ftp_close($this->ftpConnection);
            $this->ftpConnection = null;
        }
    }

    /**
     * Get media statistics
     *
     * @return  array  An array containing the number of downloaded files and the list of files
     *
     * @since   1.0.0
     */
    public function getMediaStats(): array
    {
        return [
            'downloaded' => count($this->downloadedFiles),
            'files' => array_keys($this->downloadedFiles)
        ];
    }

    /**
     * Test FTP connection and auto-detect document root
     *
     * @param   array   $config        The FTP configuration
     * 
     * @return  array  Result containing success status and message
     *
     * @since   1.0.0
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

        // Auto-detect document root
        $detectedRoot = null;
        $commonRoots = ['httpdocs', 'public_html', 'www'];
        
        foreach ($commonRoots as $root) {
            if (@ftp_chdir($connection, $root)) {
                // Try to find wp-content directory to confirm this is the right root
                if (@ftp_chdir($connection, 'wp-content')) {
                    $detectedRoot = $root;
                    // Return to original directory
                    @ftp_chdir($connection, '/');
                    break;
                }
                // Return to original directory if wp-content not found
                @ftp_chdir($connection, '/');
            }
        }

        // Close the connection
        ftp_close($connection);
        
        // Return success with detected root info
        $result['success'] = true;
        if ($detectedRoot) {
            $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_SUCCESS', $config['host']) . 
                               " Document root detected: \"{$detectedRoot}\" with WordPress content.";
        } else {
            $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_SUCCESS', $config['host']) . 
                               ' Warning: Could not detect document root with WordPress content.';
        }

        return $result;
    }

    /**
     * Get the expected local path structure for a WordPress media URL
     * 
     * This method shows how WordPress URLs will be mapped to local folders.
     * Example: wp-content/uploads/2024/01/image.jpg -> images/imports/2024/01/image.jpg
     *
     * @param   string  $wordpressUrl  The WordPress media URL
     *
     * @return  string|null  The expected local path structure, or null if not a valid WordPress URL
     *
     * @since   1.0.0
     */
    public function getExpectedLocalPath(string $wordpressUrl): ?string
    {
        $parsedUrl = parse_url($wordpressUrl);
        if (!$parsedUrl || empty($parsedUrl['path'])) {
            return null;
        }

        $uploadPath = $parsedUrl['path'];
        if (strpos($uploadPath, '/wp-content/uploads/') === false) {
            return null;
        }

        // Extract the part after wp-content/uploads/
        $pattern = '/.*\/wp-content\/uploads\/(.+)$/';
        if (preg_match($pattern, $uploadPath, $matches)) {
            return 'images/' . $this->storageDir . '/' . $matches[1];
        }

        return null;
    }

    /**
     * Clear the cached media data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function clearCache(): void
    {
        $this->downloadedFiles = [];
        $this->documentRootDetected = false;
    }

    /**
     * Destructor
     *
     * Cleans up the FTP connection on object destruction.
     *
     * @since   1.0.0
     */
    public function __destruct()
    {
        $this->disconnectFtp();
    }
}
