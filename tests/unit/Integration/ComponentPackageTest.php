<?php

namespace Joomla\Component\CmsMigrator\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Test class for generating component package
 */
class ComponentPackageTest extends TestCase
{
    /**
     * Test creating a zip package of the component
     * This runs at the end of the test suite to package the component
     */
    public function testCreateComponentPackage(): void
    {
        $componentPath = realpath(__DIR__ . '/../../../src/component');
        $outputPath = realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR . 'cypress' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'com_cmsmigrator.zip';
        
        // Ensure component directory exists
        $this->assertDirectoryExists($componentPath, 'Component directory does not exist');
        
        // Ensure cypress/fixtures directory exists
        $fixturesDir = dirname($outputPath);
        if (!is_dir($fixturesDir)) {
            mkdir($fixturesDir, 0755, true);
        }
        
        // Remove existing zip if present
        if (file_exists($outputPath)) {
            unlink($outputPath);
            $this->assertFileDoesNotExist($outputPath, 'Failed to remove existing zip file');
        }
        
        // Create new zip archive
        $zip = new ZipArchive();
        $result = $zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        $this->assertTrue($result === TRUE, 'Failed to create zip archive: ' . $this->getZipError($result));
        
        // Add component files to zip
        $this->addDirectoryToZip($zip, $componentPath, '');
        
        // Close the zip file
        $closeResult = $zip->close();
        $this->assertTrue($closeResult, 'Failed to close zip archive');
        
        // Verify the zip file was created
        $this->assertFileExists($outputPath, 'Component zip file was not created');
        
        // Verify zip file is not empty
        $this->assertGreaterThan(0, filesize($outputPath), 'Component zip file is empty');
        
        // Verify zip contains expected files
        $this->verifyZipContents($outputPath);
        
        echo "\n✅ Component package created successfully: cypress/fixtures/com_cmsmigrator.zip\n";
        echo "📦 Package size: " . $this->formatBytes(filesize($outputPath)) . "\n";
        echo "📁 Package location: " . $outputPath . "\n";
    }
    
    /**
     * Recursively add directory contents to zip
     */
    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPath): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $zipPath . substr($filePath, strlen($dir) + 1);
            
            // Convert Windows path separators to forward slashes for zip
            $relativePath = str_replace('\\', '/', $relativePath);
            
            if ($file->isDir()) {
                // Add directory
                $zip->addEmptyDir($relativePath);
            } elseif ($file->isFile()) {
                // Add file
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    
    /**
     * Verify zip contains expected component files
     */
    private function verifyZipContents(string $zipPath): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath);
        
        $this->assertTrue($result === TRUE, 'Failed to open zip for verification');
        
        // Expected files in the component
        $expectedFiles = [
            'cmsmigrator.xml',
            'admin/access.xml',
            'admin/script.php',
            'admin/forms/import.xml',
            'admin/language/en-GB/com_cmsmigrator.ini',
            'admin/language/en-GB/com_cmsmigrator.sys.ini',
            'admin/services/provider.php',
            'admin/sql/install.mysql.utf8.sql',
            'admin/src/Extension/CmsMigratorComponent.php',
            'admin/src/Controller/DisplayController.php',
            'admin/src/Controller/ImportController.php',
            'admin/src/Model/ImportModel.php',
            'admin/src/Model/MediaModel.php',
            'admin/src/Model/ProcessorModel.php',
            'admin/src/Table/ArticleTable.php',
            'admin/src/View/Cpanel/HtmlView.php',
            'admin/src/Event/MigrationEvent.php',
            'admin/tmpl/cpanel/default.php',
            'media/com_cmsmigrator/joomla.asset.json',
            'media/com_cmsmigrator/css/admin.css',
            'media/com_cmsmigrator/js/admin.js',
            'media/com_cmsmigrator/js/init.js',
            'media/com_cmsmigrator/js/migration-form.js'
        ];
        
        foreach ($expectedFiles as $expectedFile) {
            $index = $zip->locateName($expectedFile);
            $this->assertNotFalse($index, "Expected file not found in zip: {$expectedFile}");
        }
        
        $zip->close();
        
        echo "✅ Verified " . count($expectedFiles) . " expected files in package\n";
    }
    
    /**
     * Get human readable error message for zip errors
     */
    private function getZipError(int $code): string
    {
        switch ($code) {
            case ZipArchive::ER_OK: return 'No error';
            case ZipArchive::ER_MULTIDISK: return 'Multi-disk zip archives not supported';
            case ZipArchive::ER_RENAME: return 'Renaming temporary file failed';
            case ZipArchive::ER_CLOSE: return 'Closing zip archive failed';
            case ZipArchive::ER_SEEK: return 'Seek error';
            case ZipArchive::ER_READ: return 'Read error';
            case ZipArchive::ER_WRITE: return 'Write error';
            case ZipArchive::ER_CRC: return 'CRC error';
            case ZipArchive::ER_ZIPCLOSED: return 'Containing zip archive was closed';
            case ZipArchive::ER_NOENT: return 'No such file';
            case ZipArchive::ER_EXISTS: return 'File already exists';
            case ZipArchive::ER_OPEN: return 'Can\'t open file';
            case ZipArchive::ER_TMPOPEN: return 'Failure to create temporary file';
            case ZipArchive::ER_ZLIB: return 'Zlib error';
            case ZipArchive::ER_MEMORY: return 'Memory allocation failure';
            case ZipArchive::ER_CHANGED: return 'Entry has been changed';
            case ZipArchive::ER_COMPNOTSUPP: return 'Compression method not supported';
            case ZipArchive::ER_EOF: return 'Premature EOF';
            case ZipArchive::ER_INVAL: return 'Invalid argument';
            case ZipArchive::ER_NOZIP: return 'Not a zip archive';
            case ZipArchive::ER_INTERNAL: return 'Internal error';
            case ZipArchive::ER_INCONS: return 'Zip archive inconsistent';
            case ZipArchive::ER_REMOVE: return 'Can\'t remove file';
            case ZipArchive::ER_DELETED: return 'Entry has been deleted';
            default: return "Unknown error code: {$code}";
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}
