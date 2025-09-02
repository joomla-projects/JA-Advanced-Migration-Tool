<?php

namespace Binary\Component\CmsMigrator\Tests\Model;

use Binary\Component\CmsMigrator\Administrator\Model\MediaModel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test class for MediaModel
 *
 * @package Binary\Component\CmsMigrator\Tests\Model
 * @since   1.0.0
 */
class MediaModelTest extends TestCase
{
    /**
     * @var MediaModel
     */
    private $model;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new MediaModel();
        
        // Mock constants if not defined
        if (!defined('JPATH_ROOT')) {
            define('JPATH_ROOT', sys_get_temp_dir());
        }
    }

    /**
     * Test that the model extends BaseModel
     */
    public function testModelExtendsCorrectParent(): void
    {
        $this->assertInstanceOf(
            \Joomla\CMS\MVC\Model\BaseModel::class,
            $this->model
        );
    }

    /**
     * Test model can be instantiated
     */
    public function testModelCanBeInstantiated(): void
    {
        $this->assertInstanceOf(MediaModel::class, $this->model);
    }

    /**
     * Test setStorageDirectory method
     */
    public function testSetStorageDirectory(): void
    {
        $this->model->setStorageDirectory('custom_dir');
        
        // Use reflection to check protected properties
        $reflection = new \ReflectionClass($this->model);
        $storageDirProperty = $reflection->getProperty('storageDir');
        $storageDirProperty->setAccessible(true);
        
        $this->assertEquals('custom_dir', $storageDirProperty->getValue($this->model));
    }

    /**
     * Test setStorageDirectory sanitizes input
     */
    public function testSetStorageDirectorySanitizesInput(): void
    {
        $this->model->setStorageDirectory('custom@#$%dir');
        
        $reflection = new \ReflectionClass($this->model);
        $storageDirProperty = $reflection->getProperty('storageDir');
        $storageDirProperty->setAccessible(true);
        
        // Should sanitize to only alphanumeric, underscore, and dash
        $this->assertEquals('customdir', $storageDirProperty->getValue($this->model));
    }

    /**
     * Test setStorageDirectory uses default for empty input
     */
    public function testSetStorageDirectoryUsesDefaultForEmptyInput(): void
    {
        $this->model->setStorageDirectory('');
        
        $reflection = new \ReflectionClass($this->model);
        $storageDirProperty = $reflection->getProperty('storageDir');
        $storageDirProperty->setAccessible(true);
        
        $this->assertEquals('imports', $storageDirProperty->getValue($this->model));
    }

    /**
     * Test setDocumentRoot method
     */
    public function testSetDocumentRoot(): void
    {
        $this->model->setDocumentRoot('public_html');
        
        $this->assertEquals('public_html', $this->model->getDocumentRoot());
    }

    /**
     * Test setDocumentRoot trims slashes
     */
    public function testSetDocumentRootTrimsSlashes(): void
    {
        $this->model->setDocumentRoot('/public_html/');
        
        $this->assertEquals('public_html', $this->model->getDocumentRoot());
    }

    /**
     * Test setDocumentRoot uses default for empty input
     */
    public function testSetDocumentRootUsesDefaultForEmptyInput(): void
    {
        $this->model->setDocumentRoot('');
        
        $this->assertEquals('httpdocs', $this->model->getDocumentRoot());
    }

    /**
     * Test getDocumentRoot returns default value
     */
    public function testGetDocumentRootReturnsDefault(): void
    {
        $this->assertEquals('httpdocs', $this->model->getDocumentRoot());
    }

    /**
     * Test migrateMediaInContent returns content unchanged for empty input
     */
    public function testMigrateMediaInContentReturnsUnchangedForEmptyInput(): void
    {
        $config = [];
        $content = '';
        
        $result = $this->model->migrateMediaInContent($config, $content);
        
        $this->assertEquals('', $result);
    }

    /**
     * Test migrateMediaInContent returns content unchanged when no images found
     */
    public function testMigrateMediaInContentReturnsUnchangedWhenNoImages(): void
    {
        $config = [];
        $content = '<p>This is just text content without any images.</p>';
        
        $result = $this->model->migrateMediaInContent($config, $content);
        
        $this->assertEquals($content, $result);
    }

    /**
     * Test extractImageUrls method (if accessible through reflection)
     */
    public function testExtractImageUrls(): void
    {
        $content = '<img src="http://example.com/image1.jpg" alt="Test"> <img src="/local/image2.png">';
        
        $reflection = new \ReflectionClass($this->model);
        
        // Check if extractImageUrls method exists
        if ($reflection->hasMethod('extractImageUrls')) {
            $method = $reflection->getMethod('extractImageUrls');
            $method->setAccessible(true);
            
            $urls = $method->invoke($this->model, $content);
            
            $this->assertIsArray($urls);
            $this->assertContains('http://example.com/image1.jpg', $urls);
            $this->assertContains('/local/image2.png', $urls);
        } else {
            $this->markTestSkipped('extractImageUrls method not accessible for testing');
        }
    }

    /**
     * Test testConnection method with invalid configuration
     */
    public function testConnectionWithInvalidConfig(): void
    {
        $invalidConfig = [
            'connection_type' => 'ftp',
            'host' => '',
            'username' => '',
            'password' => ''
        ];
        
        $result = $this->model->testConnection($invalidConfig);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test connection type validation
     */
    public function testConnectionTypeValidation(): void
    {
        $reflection = new \ReflectionClass($this->model);
        $connectionTypeProperty = $reflection->getProperty('connectionType');
        $connectionTypeProperty->setAccessible(true);
        
        // Default should be 'ftp'
        $this->assertEquals('ftp', $connectionTypeProperty->getValue($this->model));
    }
}
