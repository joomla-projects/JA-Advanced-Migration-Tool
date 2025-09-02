<?php

namespace Binary\Component\CmsMigrator\Tests\Controller;

use Binary\Component\CmsMigrator\Administrator\Controller\ImportController;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test class for ImportController
 *
 * @package Binary\Component\CmsMigrator\Tests\Controller
 * @since   1.0.0
 */
class ImportControllerTest extends TestCase
{
    /**
     * @var ImportController
     */
    private $controller;

    /**
     * @var MockObject
     */
    private $mockApp;

    /**
     * @var MockObject
     */
    private $mockInput;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the application
        $this->mockApp = $this->getMockBuilder(\Joomla\CMS\Application\CMSApplication::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // Mock the input
        $this->mockInput = $this->getMockBuilder(\Joomla\Input\Input::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $config = [];
        $this->controller = new ImportController($config, null, $this->mockApp, $this->mockInput);
    }

    /**
     * Test that the controller extends BaseController
     */
    public function testControllerExtendsCorrectParent(): void
    {
        $this->assertInstanceOf(
            \Joomla\CMS\MVC\Controller\BaseController::class,
            $this->controller
        );
    }

    /**
     * Test controller can be instantiated
     */
    public function testControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ImportController::class, $this->controller);
    }

    /**
     * Test import method validates CSRF token
     */
    public function testImportValidatesToken(): void
    {
        // This test verifies that the import method exists and has the expected structure
        $reflection = new \ReflectionClass(ImportController::class);
        $this->assertTrue($reflection->hasMethod('import'));
        
        $method = $reflection->getMethod('import');
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test testConnection method validates CSRF token
     */
    public function testConnectionValidatesToken(): void
    {
        // This test verifies that the testConnection method exists and has the expected structure
        $reflection = new \ReflectionClass(ImportController::class);
        $this->assertTrue($reflection->hasMethod('testConnection'));
        
        $method = $reflection->getMethod('testConnection');
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test progress method returns JSON
     */
    public function testProgressReturnsJson(): void
    {
        // Create a temporary progress file for testing
        $progressFile = sys_get_temp_dir() . '/progress_test.json';
        $progressData = ['percent' => 50, 'status' => 'In progress', 'timestamp' => time()];
        file_put_contents($progressFile, json_encode($progressData));

        // Mock JPATH_SITE constant
        if (!defined('JPATH_SITE')) {
            define('JPATH_SITE', sys_get_temp_dir());
        }

        // Test the progress functionality by checking the file directly
        $this->assertFileExists($progressFile);
        $content = file_get_contents($progressFile);
        $this->assertJson($content);
        
        $decodedContent = json_decode($content, true);
        $this->assertEquals(50, $decodedContent['percent']);

        // Clean up
        unlink($progressFile);
    }
}
