<?php

namespace Joomla\Component\CmsMigrator\Tests\Helper;

use PHPUnit\Framework\TestCase;

/**
 * Test helper class providing common utilities for unit tests
 *
 * @package Joomla\Component\CmsMigrator\Tests\Helper
 * @since   1.0.0
 */
class TestHelper
{
    /**
     * Create a temporary file with specified content
     *
     * @param   string  $content  The content to write to the file
     * @param   string  $prefix   The prefix for the temporary file name
     *
     * @return  string  The path to the created temporary file
     */
    public static function createTempFile(string $content = '', string $prefix = 'test_'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), $prefix);
        if ($content) {
            file_put_contents($tempFile, $content);
        }
        return $tempFile;
    }

    /**
     * Create a temporary directory
     *
     * @param   string  $prefix  The prefix for the temporary directory name
     *
     * @return  string  The path to the created temporary directory
     */
    public static function createTempDir(string $prefix = 'test_dir_'): string
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid($prefix);
        mkdir($tempDir, 0755, true);
        return $tempDir;
    }

    /**
     * Recursively remove a directory and its contents
     *
     * @param   string  $dir  The directory to remove
     *
     * @return  void
     */
    public static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getRealPath());
            } else {
                unlink($fileinfo->getRealPath());
            }
        }

        rmdir($dir);
    }

    /**
     * Create a mock file upload array
     *
     * @param   string   $filePath  The path to the source file
     * @param   string   $name      The original file name
     * @param   string   $type      The MIME type
     * @param   integer  $error     The upload error code
     *
     * @return  array    The mock file upload array
     */
    public static function createMockFileUpload(
        string $filePath = '',
        string $name = 'test.json',
        string $type = 'application/json',
        int $error = UPLOAD_ERR_OK
    ): array {
        if (!$filePath) {
            $filePath = self::createTempFile('{"test": "data"}');
        }

        return [
            'name' => $name,
            'type' => $type,
            'tmp_name' => $filePath,
            'error' => $error,
            'size' => file_exists($filePath) ? filesize($filePath) : 0
        ];
    }

    /**
     * Create sample JSON data for testing
     *
     * @param   string  $type  The type of sample data ('json', 'wordpress', 'minimal')
     *
     * @return  array   The sample data array
     */
    public static function createSampleData(string $type = 'json'): array
    {
        switch ($type) {
            case 'wordpress':
                return [
                    'itemListElement' => [
                        [
                            '@type' => 'Article',
                            'headline' => 'Sample WordPress Article',
                            'articleBody' => 'This is a sample WordPress article content.',
                            'author' => [
                                '@type' => 'Person',
                                'name' => 'Test Author'
                            ],
                            'datePublished' => '2025-01-01T12:00:00+00:00'
                        ]
                    ]
                ];

            case 'minimal':
                return [
                    'users' => [],
                    'post_types' => [],
                    'taxonomies' => []
                ];

            case 'json':
            default:
                return [
                    'users' => [
                        [
                            'id' => 1,
                            'username' => 'testuser',
                            'email' => 'test@example.com',
                            'name' => 'Test User',
                            'registered' => '2025-01-01 12:00:00'
                        ]
                    ],
                    'post_types' => [
                        'post' => [
                            [
                                'id' => 1,
                                'title' => 'Sample Article',
                                'content' => 'This is sample article content.',
                                'author_id' => 1,
                                'created' => '2025-01-01 12:00:00',
                                'status' => 'published'
                            ]
                        ]
                    ],
                    'taxonomies' => [
                        [
                            'id' => 1,
                            'name' => 'Sample Category',
                            'type' => 'category',
                            'parent_id' => 0
                        ]
                    ],
                    'navigation_menus' => [
                        'primary' => [
                            'name' => 'Primary Menu',
                            'items' => [
                                [
                                    'title' => 'Home',
                                    'url' => '/',
                                    'type' => 'custom'
                                ]
                            ]
                        ]
                    ]
                ];
        }
    }

    /**
     * Get a property value from an object using reflection
     *
     * @param   object  $object        The object to inspect
     * @param   string  $propertyName  The name of the property
     *
     * @return  mixed   The property value
     */
    public static function getPropertyValue(object $object, string $propertyName)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Set a property value on an object using reflection
     *
     * @param   object  $object        The object to modify
     * @param   string  $propertyName  The name of the property
     * @param   mixed   $value         The value to set
     *
     * @return  void
     */
    public static function setPropertyValue(object $object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Call a private or protected method using reflection
     *
     * @param   object  $object      The object to call the method on
     * @param   string  $methodName  The name of the method
     * @param   array   $args        The arguments to pass to the method
     *
     * @return  mixed   The method return value
     */
    public static function callMethod(object $object, string $methodName, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    /**
     * Assert that a method exists on a class
     *
     * @param   string    $className   The name of the class
     * @param   string    $methodName  The name of the method
     * @param   TestCase  $testCase    The test case instance for assertions
     *
     * @return  void
     */
    public static function assertMethodExists(string $className, string $methodName, TestCase $testCase): void
    {
        $reflection = new \ReflectionClass($className);
        $testCase->assertTrue(
            $reflection->hasMethod($methodName),
            "Method {$methodName} does not exist on class {$className}"
        );
    }

    /**
     * Assert that a property exists on a class
     *
     * @param   string    $className     The name of the class
     * @param   string    $propertyName  The name of the property
     * @param   TestCase  $testCase      The test case instance for assertions
     *
     * @return  void
     */
    public static function assertPropertyExists(string $className, string $propertyName, TestCase $testCase): void
    {
        $reflection = new \ReflectionClass($className);
        $testCase->assertTrue(
            $reflection->hasProperty($propertyName),
            "Property {$propertyName} does not exist on class {$className}"
        );
    }

    /**
     * Create a mock database driver
     *
     * @param   object  $testCase  The test case instance
     *
     * @return  \PHPUnit\Framework\MockObject\MockObject
     */
    public static function createMockDatabase($testCase = null): \PHPUnit\Framework\MockObject\MockObject
    {
        if (!$testCase) {
            // Create a temporary test case instance
            $testCase = new class extends \PHPUnit\Framework\TestCase {};
        }
        return $testCase->createMock(\Joomla\Database\DatabaseDriver::class);
    }

    /**
     * Create a mock application
     *
     * @param   object  $testCase  The test case instance
     *
     * @return  \PHPUnit\Framework\MockObject\MockObject
     */
    public static function createMockApplication($testCase = null): \PHPUnit\Framework\MockObject\MockObject
    {
        if (!$testCase) {
            // Create a temporary test case instance
            $testCase = new class extends \PHPUnit\Framework\TestCase {};
        }
        return $testCase->createMock(\Joomla\CMS\Application\CMSApplication::class);
    }

    /**
     * Create a mock input object
     *
     * @param   object  $testCase  The test case instance
     *
     * @return  \PHPUnit\Framework\MockObject\MockObject
     */
    public static function createMockInput($testCase = null): \PHPUnit\Framework\MockObject\MockObject
    {
        if (!$testCase) {
            // Create a temporary test case instance
            $testCase = new class extends \PHPUnit\Framework\TestCase {};
        }
        return $testCase->createMock(\Joomla\Input\Input::class);
    }

    /**
     * Assert that a JSON string is valid
     *
     * @param   string    $json      The JSON string to validate
     * @param   TestCase  $testCase  The test case instance for assertions
     *
     * @return  void
     */
    public static function assertValidJson(string $json, TestCase $testCase): void
    {
        json_decode($json);
        $testCase->assertEquals(JSON_ERROR_NONE, json_last_error(), 'Invalid JSON: ' . json_last_error_msg());
    }

    /**
     * Clean up test files and directories
     *
     * @param   array  $paths  Array of file and directory paths to clean up
     *
     * @return  void
     */
    public static function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                self::removeDir($path);
            }
        }
    }
}
