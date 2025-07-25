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
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

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
     * SFTP connection object
     *
     * @var    SFTP|null
     * @since  1.0.0
     */
    protected $sftpConnection;

    /**
     * Connection type (ftp or sftp)
     *
     * @var    string
     * @since  1.0.0
     */
    protected $connectionType = 'ftp';

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
     * @param   array   $config        The connection configuration
     * @param   string  $content       The content with media URLs
     * @param   string  $sourceUrl     The source URL (optional)
     *
     * @return  string  The content with migrated media URLs
     *
     * @since   1.0.0
     */
    public function migrateMediaInContent(array $config, string $content, string $sourceUrl = ''): string
    {
        if (empty($content)) {
            return $content;
        }

        $imageUrls = $this->extractImageUrls($content);

        if (empty($imageUrls)) {
            return $content;
        }

        if (!$this->connect($config)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_CMSMIGRATOR_MEDIA_CONNECTION_FAILED'), 'warning');
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

        $this->disconnect();
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
    public function extractImageUrlsFromContent(string $content): array
    {
        return $this->extractImageUrls($content);
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

        // Match standard WordPress uploads URLs
        preg_match_all('/https?:\/\/[^\/]+\/wp-content\/uploads\/[^\s"\'<>]+\.(jpg|jpeg|png|gif|webp)/i', $content, $wpMatches);
        if (!empty($wpMatches[0])) {
            $imageUrls = array_merge($imageUrls, $wpMatches[0]);
        }
        
        // Match direct uploads folder URLs
        preg_match_all('/https?:\/\/[^\/]+\/uploads\/[^\s"\'<>]+\.(jpg|jpeg|png|gif|webp)/i', $content, $directMatches);
        if (!empty($directMatches[0])) {
            $imageUrls = array_merge($imageUrls, $directMatches[0]);
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
        
        // Check for different WordPress upload path patterns
        $isWordPressUpload = false;
        $relativePath = '';
        
        if (strpos($uploadPath, '/wp-content/uploads/') !== false) {
            // Standard WordPress structure: /wp-content/uploads/...
            $isWordPressUpload = true;
            preg_match('/.*\/wp-content\/uploads\/(.+)$/', $uploadPath, $matches);
            $relativePath = $matches[1] ?? '';
        } elseif (strpos($uploadPath, '/uploads/') !== false) {
            // Direct uploads folder: /uploads/...
            $isWordPressUpload = true;
            preg_match('/.*\/uploads\/(.+)$/', $uploadPath, $matches);
            $relativePath = $matches[1] ?? '';
        }
        
        if (!$isWordPressUpload || empty($relativePath)) {
            Factory::getApplication()->enqueueMessage("Not a WordPress upload path: $uploadPath", 'warning');
            return null;
        }

        $pathInfo = pathinfo($relativePath);
        $resizedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-768x512.' . $pathInfo['extension'];
        $originalPath = $pathInfo['dirname'] . '/' . $pathInfo['basename'];

        // Generate candidate remote paths based on detected structure
        $candidatePaths = $this->generateCandidateRemotePaths($resizedPath, $originalPath);

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

            if ($this->downloadFile($remotePath, $localFilePath)) {
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
     * Generate candidate remote paths for different WordPress structures
     *
     * @param   string  $resizedPath   The resized image path (relative)
     * @param   string  $originalPath  The original image path (relative)
     *
     * @return  array   Array of candidate remote paths to try
     *
     * @since   1.0.0
     */
    protected function generateCandidateRemotePaths(string $resizedPath, string $originalPath): array
    {
        $candidatePaths = [];
        $documentRoot = $this->documentRoot === '.' ? '' : $this->documentRoot;
        
        // Try different WordPress structure patterns
        $structures = [
            // Standard structure: {documentRoot}/wp-content/uploads/...
            ($documentRoot ? $documentRoot . '/' : '') . 'wp-content/uploads/',
            // Direct uploads: {documentRoot}/uploads/...
            ($documentRoot ? $documentRoot . '/' : '') . 'uploads/',
            // Root wp-content: wp-content/uploads/...
            'wp-content/uploads/',
            // Root uploads: uploads/...
            'uploads/'
        ];
        
        foreach ($structures as $structure) {
            // Add resized version first (usually smaller file)
            $candidatePaths[] = $structure . $resizedPath;
            // Add original version
            $candidatePaths[] = $structure . $originalPath;
        }
        
        return array_unique($candidatePaths);
    }

    /**
     * Download file via FTP or SFTP
     *
     * @param   string  $remotePath  The remote file path
     * @param   string  $localPath   The local file path
     *
     * @return  bool  True on success, false on failure
     *
     * @since   1.0.0
     */
    protected function downloadFile(string $remotePath, string $localPath): bool
    {
        if ($this->connectionType === 'sftp') {
            return $this->downloadFileViaSftp($remotePath, $localPath);
        } else {
            return $this->downloadFileViaFtp($remotePath, $localPath);
        }
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
     * Download file via SFTP
     *
     * @param   string  $remotePath  The remote file path
     * @param   string  $localPath   The local file path
     *
     * @return  bool  True on success, false on failure
     *
     * @since   1.0.0
     */
    protected function downloadFileViaSftp(string $remotePath, string $localPath): bool
    {
        if (!$this->sftpConnection) {
            return false;
        }

        $localDir = dirname($localPath);
        if (!Folder::exists($localDir)) {
            Folder::create($localDir);
        }

        try {
            $result = $this->sftpConnection->get($remotePath, $localPath);
            
            if ($result !== false) {
                return true;
            }
        } catch (\Exception $e) {
            // Log error if needed
        }

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
        
        // Extract the path after direct uploads/
        $pattern = '/.*\/uploads\/(.+)$/';
        if (preg_match($pattern, $remotePath, $matches)) {
            // Return the uploads structure (e.g., 2024/01/image.jpg)
            return $matches[1];
        }
        
        // Fallback: clean the path but preserve some structure
        $cleanPath = $remotePath;
        
        // Remove document root if present
        if ($this->documentRoot !== '.' && strpos($cleanPath, $this->documentRoot . '/') === 0) {
            $cleanPath = substr($cleanPath, strlen($this->documentRoot . '/'));
        }
        
        // Remove wp-content/uploads/ or uploads/ prefix
        $cleanPath = preg_replace('/^(wp-content\/)?uploads\//', '', $cleanPath);
        
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
        if ((!$this->ftpConnection && !$this->sftpConnection) || $this->documentRootDetected) {
            return;
        }

        // Test different WordPress structure scenarios
        $testScenarios = [
            // Standard document roots with wp-content
            ['httpdocs', 'wp-content'],
            ['public_html', 'wp-content'],
            ['www', 'wp-content'],
            // WordPress in root directory
            ['', 'wp-content'],
            // Direct uploads folder scenarios
            ['httpdocs', 'uploads'],
            ['public_html', 'uploads'],
            ['www', 'uploads'],
            ['', 'uploads']
        ];

        foreach ($testScenarios as [$root, $contentPath]) {
            $canAccess = false;
            $hasWordPressContent = false;
            
            if ($this->connectionType === 'sftp' && $this->sftpConnection) {
                try {
                    $checkPath = $root ? $root . '/' . $contentPath : $contentPath;
                    
                    // Check if the root directory exists (or skip if empty root)
                    if (empty($root) || $this->sftpConnection->is_dir($root)) {
                        $canAccess = true;
                        
                        // Check for WordPress content structure
                        if ($this->sftpConnection->is_dir($checkPath)) {
                            $hasWordPressContent = true;
                            
                            // For wp-content, also check for uploads subdirectory
                            if ($contentPath === 'wp-content') {
                                $uploadsPath = $checkPath . '/uploads';
                                if ($this->sftpConnection->is_dir($uploadsPath)) {
                                    $hasWordPressContent = true;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next scenario
                }
            } else if ($this->ftpConnection) {
                $originalDir = @ftp_pwd($this->ftpConnection);
                
                // Check if we can access the root directory (or skip if empty root)
                if (empty($root) || @ftp_chdir($this->ftpConnection, $root)) {
                    $canAccess = true;
                    
                    // Check for WordPress content structure
                    if (@ftp_chdir($this->ftpConnection, $contentPath)) {
                        $hasWordPressContent = true;
                        
                        // For wp-content, also check for uploads subdirectory
                        if ($contentPath === 'wp-content') {
                            if (@ftp_chdir($this->ftpConnection, 'uploads')) {
                                $hasWordPressContent = true;
                                // Return to wp-content
                                @ftp_chdir($this->ftpConnection, '..');
                            }
                        }
                        
                        // Return to root
                        @ftp_chdir($this->ftpConnection, $originalDir ?: '/');
                    } else {
                        // Return to original directory if content path not found
                        @ftp_chdir($this->ftpConnection, $originalDir ?: '/');
                    }
                } else {
                    // Return to original directory if root change failed
                    @ftp_chdir($this->ftpConnection, $originalDir ?: '/');
                }
            }
            
            if ($canAccess && $hasWordPressContent) {
                $this->documentRoot = $root ?: '.';
                $this->documentRootDetected = true;
                
                $detectedStructure = $root ? "{$root}/{$contentPath}" : $contentPath;
                Factory::getApplication()->enqueueMessage(
                    "✅ WordPress structure auto-detected: {$detectedStructure} (Document root: {$this->documentRoot})",
                    'info'
                );
                
                return;
            }
        }
        
        // If no valid structure found, use default and mark as detected to avoid repeated attempts
        $this->documentRootDetected = true;
        Factory::getApplication()->enqueueMessage(
            "⚠️ Could not auto-detect WordPress structure. Using default document root: {$this->documentRoot}",
            'warning'
        );
    }

    /**
     * Connect to FTP or SFTP server
     *
     * @param   array  $config  The connection configuration
     *
     * @return  bool  True on success, false on failure
     *
     * @since   1.0.0
     */
    protected function connect(array $config): bool
    {
        $this->connectionType = $config['connection_type'] ?? 'ftp';
        
        if ($this->connectionType === 'sftp') {
            return $this->connectSftp($config);
        } else {
            return $this->connectFtp($config);
        }
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
     * Connect to SFTP server
     *
     * @param   array  $config  The SFTP configuration
     *
     * @return  bool  True on success, false on failure
     *
     * @since   1.0.0
     */
    protected function connectSftp(array $config): bool
    {
        if ($this->sftpConnection) {
            return true;
        }

        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            Factory::getApplication()->enqueueMessage('SFTP configuration incomplete', 'error');
            return false;
        }

        try {
            $this->sftpConnection = new SFTP($config['host'], $config['port'] ?? 22);
            
            if (!$this->sftpConnection->login($config['username'], $config['password'])) {
                Factory::getApplication()->enqueueMessage('SFTP login failed', 'error');
                $this->sftpConnection = null;
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage("Failed to connect to SFTP server: {$config['host']} - " . $e->getMessage(), 'error');
            $this->sftpConnection = null;
            return false;
        }
    }

    /**
     * Disconnect from FTP or SFTP server
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function disconnect(): void
    {
        if ($this->connectionType === 'sftp') {
            $this->disconnectSftp();
        } else {
            $this->disconnectFtp();
        }
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
     * Disconnect from SFTP server
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function disconnectSftp(): void
    {
        if ($this->sftpConnection) {
            $this->sftpConnection->disconnect();
            $this->sftpConnection = null;
        }
    }

    /**
     * Get the planned Joomla URL for a WordPress media URL (without downloading)
     *
     * @param   string  $wordpressUrl  The WordPress media URL
     *
     * @return  string|null  The planned Joomla URL or null if invalid
     *
     * @since   1.0.0
     */
    public function getPlannedJoomlaUrl(string $wordpressUrl): ?string
    {
        $parsedUrl = parse_url($wordpressUrl);
        if (!$parsedUrl || empty($parsedUrl['path'])) {
            return null;
        }

        $uploadPath = $parsedUrl['path'];
        
        // Check for standard WordPress structure: /wp-content/uploads/
        if (strpos($uploadPath, '/wp-content/uploads/') !== false) {
            $pattern = '/.*\/wp-content\/uploads\/(.+)$/';
            if (preg_match($pattern, $uploadPath, $matches)) {
                return $this->mediaBaseUrl . $matches[1];
            }
        }
        
        // Check for direct uploads structure: /uploads/
        if (strpos($uploadPath, '/uploads/') !== false) {
            $pattern = '/.*\/uploads\/(.+)$/';
            if (preg_match($pattern, $uploadPath, $matches)) {
                return $this->mediaBaseUrl . $matches[1];
            }
        }

        return null;
    }

    /**
     * Batch download multiple media files in parallel using FTP or SFTP
     *
     * @param   array  $mediaUrls   Array of media URLs to download
     * @param   array  $config      Connection configuration
     *
     * @return  array  Array of results with success/failure for each URL
     *
     * @since   1.0.0
     */
    public function batchDownloadMedia(array $mediaUrls, array $config): array
    {
        if (empty($mediaUrls)) {
            return [];
        }

        if (!$this->connect($config)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_CMSMIGRATOR_MEDIA_CONNECTION_FAILED'), 'warning');
            return [];
        }

        // Auto-detect document root on first use
        if (!$this->documentRootDetected) {
            $this->autoDetectDocumentRoot();
        }

        $results = [];
        $downloadTasks = [];

        // Prepare download tasks
        foreach ($mediaUrls as $imageUrl) {
            $downloadPaths = $this->prepareDownloadPaths($imageUrl);
            if (!empty($downloadPaths)) {
                $downloadTasks[$imageUrl] = $downloadPaths;
            }
        }

        if (empty($downloadTasks)) {
            return $results;
        }

        Factory::getApplication()->enqueueMessage(
            sprintf('Starting batch download of %d media files...', count($downloadTasks)),
            'info'
        );

        // Process downloads in smaller parallel batches to avoid overwhelming the server
        $batchSize = min(10, count($downloadTasks)); // Max 10 parallel connections
        $taskBatches = array_chunk($downloadTasks, $batchSize, true);

        foreach ($taskBatches as $batch) {
            $this->processBatchDownload($batch, $results);
        }

        $successCount = count(array_filter($results, function($result) { return $result['success']; }));
        Factory::getApplication()->enqueueMessage(
            sprintf('✅ Batch download complete: %d/%d files downloaded successfully', $successCount, count($results)),
            'info'
        );

        return $results;
    }

    /**
     * Prepare download paths for a media URL
     *
     * @param   string  $imageUrl  The image URL
     *
     * @return  array  Array of remote and local paths to try
     *
     * @since   1.0.0
     */
    protected function prepareDownloadPaths(string $imageUrl): array
    {
        $parsedUrl = parse_url($imageUrl);
        if (!$parsedUrl || empty($parsedUrl['path'])) {
            return [];
        }

        $uploadPath = $parsedUrl['path'];
        
        // Check for different WordPress upload path patterns
        $isWordPressUpload = false;
        $relativePath = '';
        
        if (strpos($uploadPath, '/wp-content/uploads/') !== false) {
            // Standard WordPress structure: /wp-content/uploads/...
            $isWordPressUpload = true;
            preg_match('/.*\/wp-content\/uploads\/(.+)$/', $uploadPath, $matches);
            $relativePath = $matches[1] ?? '';
        } elseif (strpos($uploadPath, '/uploads/') !== false) {
            // Direct uploads folder: /uploads/...
            $isWordPressUpload = true;
            preg_match('/.*\/uploads\/(.+)$/', $uploadPath, $matches);
            $relativePath = $matches[1] ?? '';
        }
        
        if (!$isWordPressUpload || empty($relativePath)) {
            return [];
        }

        $pathInfo = pathinfo($relativePath);
        $resizedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-768x512.' . $pathInfo['extension'];
        $originalPath = $pathInfo['dirname'] . '/' . $pathInfo['basename'];

        $paths = [];
        
        // Generate candidate remote paths based on detected structure
        $candidatePaths = $this->generateCandidateRemotePaths($resizedPath, $originalPath);
        
        foreach ($candidatePaths as $remotePath) {
            $localFileName = $this->getLocalFileName($remotePath);
            $localFilePath = $this->mediaBasePath . $localFileName;

            // Skip if already downloaded
            if (isset($this->downloadedFiles[$remotePath]) || File::exists($localFilePath)) {
                continue;
            }

            $paths[] = [
                'remote' => $remotePath,
                'local' => $localFilePath,
                'url' => $this->mediaBaseUrl . $localFileName
            ];
        }

        return $paths;
    }

    /**
     * Process a batch of downloads in parallel
     *
     * @param   array  $downloadTasks  Array of download tasks
     * @param   array  &$results       Results array passed by reference
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function processBatchDownload(array $downloadTasks, array &$results): void
    {
        foreach ($downloadTasks as $imageUrl => $paths) {
            $downloaded = false;
            
            foreach ($paths as $pathInfo) {
                $localDir = dirname($pathInfo['local']);
                if (!Folder::exists($localDir)) {
                    Folder::create($localDir);
                }

                if ($this->downloadFile($pathInfo['remote'], $pathInfo['local'])) {
                    $this->downloadedFiles[$pathInfo['remote']] = $pathInfo['url'];
                    $results[$imageUrl] = [
                        'success' => true,
                        'local_path' => $pathInfo['local'],
                        'new_url' => $pathInfo['url']
                    ];
                    $downloaded = true;
                    break;
                }
            }

            if (!$downloaded) {
                $results[$imageUrl] = [
                    'success' => false,
                    'error' => 'File not found in any resolution'
                ];
            }
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
     * Test FTP or SFTP connection and auto-detect document root
     *
     * @param   array   $config        The connection configuration
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

        $connectionType = $config['connection_type'] ?? 'ftp';

        // Validate configuration
        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            $result['message'] = Text::_('COM_CMSMIGRATOR_MEDIA_CONNECTION_FIELDS_REQUIRED');
            return $result;
        }

        if ($connectionType === 'sftp') {
            return $this->testSftpConnection($config);
        } else {
            return $this->testFtpConnection($config);
        }
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
    protected function testFtpConnection(array $config): array
    {
        $result = [
            'success' => false,
            'message' => ''
        ];

        // Try to connect
        $connection = @ftp_connect($config['host'], $config['port'] ?? 21, 15);
        
        if (!$connection) {
            $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_FAILED', 'Could not connect to FTP server');
            return $result;
        }

        // Try to login
        $loginResult = @ftp_login($connection, $config['username'], $config['password']);
        
        if (!$loginResult) {
            ftp_close($connection);
            $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_FAILED', 'Invalid FTP credentials');
            return $result;
        }

        // Set passive mode if requested
        if (!empty($config['passive'])) {
            ftp_pasv($connection, true);
        }

        // Auto-detect document root
        $detectedRoot = $this->detectDocumentRootFtp($connection);

        // Close the connection
        ftp_close($connection);
        
        // Return success with detected root info
        $result['success'] = true;
        if ($detectedRoot) {
            $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_SUCCESS', $config['host']) . 
                               "<br> Document root detected: \"{$detectedRoot}\" with WordPress content.";
        } else {
            $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_SUCCESS', $config['host']) . 
                               '<br> Warning: Could not detect document root with WordPress content.';
        }

        return $result;
    }

    /**
     * Test SFTP connection and auto-detect document root
     *
     * @param   array   $config        The SFTP configuration
     * 
     * @return  array  Result containing success status and message
     *
     * @since   1.0.0
     */
    protected function testSftpConnection(array $config): array
    {
        $result = [
            'success' => false,
            'message' => ''
        ];

        try {
            $sftp = new SFTP($config['host'], $config['port'] ?? 22);
            
            if (!$sftp->login($config['username'], $config['password'])) {
                $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_FAILED', 'Invalid SFTP credentials');
                return $result;
            }

            // Auto-detect document root
            $detectedRoot = $this->detectDocumentRootSftp($sftp);

            // Disconnect
            $sftp->disconnect();
            
            // Return success with detected root info
            $result['success'] = true;
            if ($detectedRoot) {
                $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_SUCCESS', $config['host']) . 
                                   "<br> Document root detected: \"{$detectedRoot}\" with WordPress content.";
            } else {
                $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_SUCCESS', $config['host']) . 
                                   '<br> Warning: Could not detect document root with WordPress content.';
            }

            return $result;
        } catch (\Exception $e) {
            $result['message'] = Text::sprintf('COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_FAILED', 'Could not connect to SFTP server: ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * Detect document root via FTP
     *
     * @param   resource  $connection  The FTP connection
     * 
     * @return  string|null  The detected document root or null
     *
     * @since   1.0.0
     */
    protected function detectDocumentRootFtp($connection): ?string
    {
        $commonRoots = ['httpdocs', 'public_html', 'www'];
        
        foreach ($commonRoots as $root) {
            if (@ftp_chdir($connection, $root)) {
                // Try to find wp-content directory to confirm this is the right root
                if (@ftp_chdir($connection, 'wp-content')) {
                    // Return to original directory
                    @ftp_chdir($connection, '/');
                    return $root;
                }
                // Return to original directory if wp-content not found
                @ftp_chdir($connection, '/');
            }
        }
        
        return null;
    }

    /**
     * Detect document root via SFTP
     *
     * @param   SFTP  $sftp  The SFTP connection
     * 
     * @return  string|null  The detected document root or null
     *
     * @since   1.0.0
     */
    protected function detectDocumentRootSftp(SFTP $sftp): ?string
    {
        $commonRoots = ['httpdocs', 'public_html', 'www'];
        
        foreach ($commonRoots as $root) {
            try {
                // Check if directory exists and has wp-content
                if ($sftp->is_dir($root) && $sftp->is_dir($root . '/wp-content')) {
                    return $root;
                }
            } catch (\Exception $e) {
                // Continue to next root
            }
        }
        
        return null;
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
        
        // Check for standard WordPress structure: /wp-content/uploads/
        if (strpos($uploadPath, '/wp-content/uploads/') !== false) {
            $pattern = '/.*\/wp-content\/uploads\/(.+)$/';
            if (preg_match($pattern, $uploadPath, $matches)) {
                return 'images/' . $this->storageDir . '/' . $matches[1];
            }
        }
        
        // Check for direct uploads structure: /uploads/
        if (strpos($uploadPath, '/uploads/') !== false) {
            $pattern = '/.*\/uploads\/(.+)$/';
            if (preg_match($pattern, $uploadPath, $matches)) {
                return 'images/' . $this->storageDir . '/' . $matches[1];
            }
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
     * Cleans up the FTP and SFTP connections on object destruction.
     *
     * @since   1.0.0
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
