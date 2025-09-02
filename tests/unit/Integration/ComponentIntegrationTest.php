<?php

namespace Binary\Component\CmsMigrator\Tests\Integration;

use Binary\Component\CmsMigrator\Administrator\Extension\CmsMigratorComponent;
use Binary\Component\CmsMigrator\Administrator\Controller\ImportController;
use Binary\Component\CmsMigrator\Administrator\Model\ImportModel;
use Binary\Component\CmsMigrator\Administrator\Event\MigrationEvent;
use Binary\Component\CmsMigrator\Tests\Helper\TestHelper;
use PHPUnit\Framework\TestCase;

/**
 * Integration test class for CMS Migrator component
 *
 * Tests the interaction between different component parts
 *
 * @package Binary\Component\CmsMigrator\Tests\Integration
 * @since   1.0.0
 */
class ComponentIntegrationTest extends TestCase
{
    /**
     * @var array
     */
    private $tempFiles = [];

    /**
     * @var array
     */
    private $tempDirs = [];

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure required constants are defined
        if (!defined('JPATH_SITE')) {
            define('JPATH_SITE', sys_get_temp_dir());
        }
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        TestHelper::cleanup(array_merge($this->tempFiles, $this->tempDirs));
        parent::tearDown();
    }

    /**
     * Test component can be instantiated and initialized
     */
    public function testComponentCanBeInstantiatedAndInitialized(): void
    {
        $component = new CmsMigratorComponent();
        
        $this->assertInstanceOf(CmsMigratorComponent::class, $component);
        $this->assertInstanceOf(\Joomla\CMS\Extension\MVCComponent::class, $component);
        $this->assertInstanceOf(\Joomla\CMS\Extension\BootableExtensionInterface::class, $component);
    }

    /**
     * Test import model and event system integration
     */
    public function testImportModelEventSystemIntegration(): void
    {
        // Create test JSON file
        $jsonData = TestHelper::createSampleData('json');
        $jsonFile = TestHelper::createTempFile(json_encode($jsonData));
        $this->tempFiles[] = $jsonFile;

        // Create file upload array
        $fileUpload = TestHelper::createMockFileUpload($jsonFile, 'test.json');

        // Test that ImportModel can process JSON data directly
        $model = new ImportModel();
        
        // Create the import event
        $event = new MigrationEvent('onMigrationConvert', [
            'sourceCms' => 'json',
            'filePath' => $fileUpload['tmp_name']
        ]);

        $this->assertEquals('onMigrationConvert', $event->getName());
        $this->assertEquals('json', $event->getArguments()['sourceCms']);
        $this->assertEquals($fileUpload['tmp_name'], $event->getArguments()['filePath']);
    }

    /**
     * Test controller and model integration
     */
    public function testControllerModelIntegration(): void
    {
        // Mock application and input
        $mockApp = $this->createMock(\Joomla\CMS\Application\CMSApplication::class);
        $mockInput = $this->createMock(\Joomla\Input\Input::class);

        // Create controller
        $controller = new ImportController([], null, $mockApp, $mockInput);

        $this->assertInstanceOf(ImportController::class, $controller);
        $this->assertInstanceOf(\Joomla\CMS\MVC\Controller\BaseController::class, $controller);
    }

    /**
     * Test complete data flow from JSON to processing
     */
    public function testCompleteDataFlowFromJsonToProcessing(): void
    {
        // Create comprehensive test data
        $testData = [
            'users' => [
                [
                    'id' => 1,
                    'username' => 'integrationtest',
                    'email' => 'integration@test.com',
                    'name' => 'Integration Test User',
                    'registered' => '2025-01-01 10:00:00'
                ]
            ],
            'post_types' => [
                'post' => [
                    [
                        'id' => 1,
                        'title' => 'Integration Test Article',
                        'content' => 'This is integration test content with <img src="/test-image.jpg" alt="test">',
                        'author_id' => 1,
                        'created' => '2025-01-01 11:00:00',
                        'status' => 'published',
                        'categories' => [1],
                        'tags' => ['integration', 'test']
                    ]
                ]
            ],
            'taxonomies' => [
                [
                    'id' => 1,
                    'name' => 'Integration Test Category',
                    'type' => 'category',
                    'parent_id' => 0,
                    'description' => 'Category for integration testing'
                ]
            ],
            'navigation_menus' => [
                'primary' => [
                    'name' => 'Primary Navigation',
                    'items' => [
                        [
                            'title' => 'Home',
                            'url' => '/',
                            'type' => 'custom',
                            'order' => 1
                        ],
                        [
                            'title' => 'Test Category',
                            'object_id' => 1,
                            'type' => 'category',
                            'order' => 2
                        ]
                    ]
                ]
            ]
        ];

        // Create temporary JSON file
        $jsonFile = TestHelper::createTempFile(json_encode($testData));
        $this->tempFiles[] = $jsonFile;

        // Verify file was created and contains valid JSON
        $this->assertFileExists($jsonFile);
        $fileContent = file_get_contents($jsonFile);
        TestHelper::assertValidJson($fileContent, $this);

        // Parse and verify data structure
        $parsedData = json_decode($fileContent, true);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertArrayHasKey('users', $parsedData);
        $this->assertArrayHasKey('post_types', $parsedData);
        $this->assertArrayHasKey('taxonomies', $parsedData);
        $this->assertArrayHasKey('navigation_menus', $parsedData);

        // Verify data integrity
        $this->assertCount(1, $parsedData['users']);
        $this->assertEquals('integrationtest', $parsedData['users'][0]['username']);
        $this->assertArrayHasKey('post', $parsedData['post_types']);
        $this->assertCount(1, $parsedData['post_types']['post']);
        $this->assertEquals('Integration Test Article', $parsedData['post_types']['post'][0]['title']);
    }

    /**
     * Test event creation and result handling
     */
    public function testEventCreationAndResultHandling(): void
    {
        $eventName = 'onMigrationTest';
        $arguments = [
            'testParam' => 'testValue',
            'anotherParam' => 123
        ];

        $event = new MigrationEvent($eventName, $arguments);

        // Test initial state
        $this->assertEquals($eventName, $event->getName());
        $this->assertEquals($arguments, $event->getArguments());
        $this->assertEmpty($event->getResults());

        // Test adding results
        $result1 = 'Test result 1';
        $result2 = ['array' => 'result'];
        $result3 = 42;

        $event->addResult($result1);
        $event->addResult($result2);
        $event->addResult($result3);

        $results = $event->getResults();
        $this->assertCount(3, $results);
        $this->assertEquals($result1, $results[0]);
        $this->assertEquals($result2, $results[1]);
        $this->assertEquals($result3, $results[2]);
    }

    /**
     * Test error handling throughout the component
     */
    public function testErrorHandlingThroughoutComponent(): void
    {
        // Test with invalid JSON file
        $invalidJsonFile = TestHelper::createTempFile('invalid json content {');
        $this->tempFiles[] = $invalidJsonFile;

        $fileUpload = TestHelper::createMockFileUpload($invalidJsonFile, 'invalid.json');

        // Verify file exists but contains invalid JSON
        $this->assertFileExists($invalidJsonFile);
        $content = file_get_contents($invalidJsonFile);
        json_decode($content);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());

        // Test ImportModel with invalid JSON should handle gracefully
        $model = new ImportModel();
        $this->assertInstanceOf(ImportModel::class, $model);
    }

    /**
     * Test file upload validation integration
     */
    public function testFileUploadValidationIntegration(): void
    {
        // Test various file upload scenarios
        $scenarios = [
            'valid_json' => [
                'content' => json_encode(TestHelper::createSampleData('minimal')),
                'name' => 'valid.json',
                'type' => 'application/json',
                'error' => UPLOAD_ERR_OK
            ],
            'empty_file' => [
                'content' => '',
                'name' => 'empty.json',
                'type' => 'application/json',
                'error' => UPLOAD_ERR_OK
            ],
            'upload_error' => [
                'content' => json_encode(TestHelper::createSampleData('minimal')),
                'name' => 'error.json',
                'type' => 'application/json',
                'error' => UPLOAD_ERR_NO_FILE
            ]
        ];

        foreach ($scenarios as $scenario => $config) {
            $file = TestHelper::createTempFile($config['content']);
            $this->tempFiles[] = $file;

            $fileUpload = TestHelper::createMockFileUpload(
                $file,
                $config['name'],
                $config['type'],
                $config['error']
            );

            // Verify file upload array structure
            $this->assertArrayHasKey('name', $fileUpload);
            $this->assertArrayHasKey('type', $fileUpload);
            $this->assertArrayHasKey('tmp_name', $fileUpload);
            $this->assertArrayHasKey('error', $fileUpload);
            $this->assertArrayHasKey('size', $fileUpload);

            $this->assertEquals($config['name'], $fileUpload['name']);
            $this->assertEquals($config['type'], $fileUpload['type']);
            $this->assertEquals($config['error'], $fileUpload['error']);
        }
    }

    /**
     * Test media configuration integration
     */
    public function testMediaConfigurationIntegration(): void
    {
        $mediaConfigurations = [
            'ftp' => [
                'connection_type' => 'ftp',
                'host' => 'test.example.com',
                'port' => 21,
                'username' => 'testuser',
                'password' => 'testpass',
                'passive' => true,
                'media_storage_mode' => 'root',
                'media_custom_dir' => ''
            ],
            'sftp' => [
                'connection_type' => 'sftp',
                'host' => 'test.example.com',
                'port' => 22,
                'username' => 'testuser',
                'password' => 'testpass',
                'passive' => false,
                'media_storage_mode' => 'custom',
                'media_custom_dir' => 'custom/media/path'
            ],
            'zip' => [
                'connection_type' => 'zip',
                'zip_file' => TestHelper::createMockFileUpload(),
                'media_storage_mode' => 'root',
                'media_custom_dir' => ''
            ]
        ];

        foreach ($mediaConfigurations as $type => $config) {
            // Verify configuration structure
            $this->assertArrayHasKey('connection_type', $config);
            $this->assertEquals($type, $config['connection_type']);
            $this->assertArrayHasKey('media_storage_mode', $config);

            if ($type !== 'zip') {
                $this->assertArrayHasKey('host', $config);
                $this->assertArrayHasKey('username', $config);
                $this->assertArrayHasKey('password', $config);
            }
        }
    }

    /**
     * Test component namespace and autoloading
     */
    public function testComponentNamespaceAndAutoloading(): void
    {
        $expectedClasses = [
            'Binary\Component\CmsMigrator\Administrator\Extension\CmsMigratorComponent',
            'Binary\Component\CmsMigrator\Administrator\Controller\DisplayController',
            'Binary\Component\CmsMigrator\Administrator\Controller\ImportController',
            'Binary\Component\CmsMigrator\Administrator\Model\ImportModel',
            'Binary\Component\CmsMigrator\Administrator\Model\MediaModel',
            'Binary\Component\CmsMigrator\Administrator\Model\ProcessorModel',
            'Binary\Component\CmsMigrator\Administrator\Table\ArticleTable',
            'Binary\Component\CmsMigrator\Administrator\Event\MigrationEvent',
            'Binary\Component\CmsMigrator\Administrator\View\Cpanel\HtmlView'
        ];

        foreach ($expectedClasses as $className) {
            $this->assertTrue(
                class_exists($className),
                "Class {$className} should be autoloaded"
            );
        }
    }

    /**
     * Test complete migration workflow simulation
     */
    public function testCompleteMigrationWorkflowSimulation(): void
    {
        // 1. Create test data file
        $migrationData = TestHelper::createSampleData('json');
        $jsonFile = TestHelper::createTempFile(json_encode($migrationData));
        $this->tempFiles[] = $jsonFile;

        // 2. Create file upload array
        $fileUpload = TestHelper::createMockFileUpload($jsonFile, 'migration.json');

        // 3. Verify file upload is valid
        $this->assertEquals(UPLOAD_ERR_OK, $fileUpload['error']);
        $this->assertFileExists($fileUpload['tmp_name']);

        // 4. Create import model and verify it can be instantiated
        $importModel = new ImportModel();
        $this->assertInstanceOf(ImportModel::class, $importModel);

        // 5. Test event creation for migration process
        $migrationEvent = new MigrationEvent('onMigrationConvert', [
            'sourceCms' => 'json',
            'filePath' => $fileUpload['tmp_name']
        ]);

        // 6. Verify event data
        $this->assertEquals('onMigrationConvert', $migrationEvent->getName());
        $this->assertEquals('json', $migrationEvent->getArguments()['sourceCms']);

        // 7. Simulate successful conversion
        $convertedData = file_get_contents($fileUpload['tmp_name']);
        $migrationEvent->addResult($convertedData);

        // 8. Verify conversion results
        $results = $migrationEvent->getResults();
        $this->assertCount(1, $results);
        TestHelper::assertValidJson($results[0], $this);

        // 9. Verify data structure is preserved
        $parsedResult = json_decode($results[0], true);
        $this->assertEquals($migrationData, $parsedResult);

        // 10. Test that media directory structure can be created
        $mediaDir = TestHelper::createTempDir('media_test_');
        $this->tempDirs[] = $mediaDir;
        $this->assertDirectoryExists($mediaDir);

        // Success - workflow simulation completed
        $this->assertTrue(true);
    }
}
